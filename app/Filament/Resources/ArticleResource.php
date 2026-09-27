<?php

namespace App\Filament\Resources;

use App\Enums\ContentStatus;
use App\Filament\Concerns\RecordsContentReview;
use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use App\Notifications\ContentApproved;
use App\Support\ContentPublisher;
use App\Support\ContentReviewer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ArticleResource extends Resource
{
    use RecordsContentReview;

    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Yayın Yönetimi';

    protected static ?string $modelLabel = 'Makale';

    protected static ?string $pluralModelLabel = 'Makaleler';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('author_id')
                    ->label('Yazar')
                    ->relationship('author', 'name')
                    ->required(),
                Forms\Components\Select::make('magazine_issue_id')
                    ->label('Dergi Sayısı')
                    ->relationship('magazineIssue', 'title'),
                Forms\Components\TextInput::make('title')
                    ->label('Başlık')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $state, Forms\Set $set) => $set('slug', str($state)->slug())),
                Forms\Components\TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(ignoreRecord: true),
                // Faz F1: içerik yazar panelindeki editörde düzenleniyor. Filament'in (Trix)
                // editörü dipnot işaretlerini siliyor; düzenlemede sadece önizleme gösteriliyor.
                Forms\Components\RichEditor::make('content')
                    ->label('İçerik')
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->columnSpanFull(),
                Forms\Components\Placeholder::make('content_preview')
                    ->label('İçerik')
                    ->hint('Yazar panelindeki editörde düzenlenir')
                    ->content(fn (?Article $record): HtmlString => new HtmlString('<div class="prose max-w-none dark:prose-invert">'.$record?->renderedContent().'</div>'))
                    ->visible(fn (string $operation): bool => $operation !== 'create')
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->label('Durum')
                    ->options(collect(ContentStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->default(ContentStatus::Taslak->value)
                    ->required()
                    ->disabled(fn (string $operation): bool => $operation !== 'create')
                    ->dehydrated(fn (string $operation): bool => $operation === 'create')
                    ->helperText(fn (string $operation): ?string => $operation === 'create'
                        ? null
                        : 'Durum sadece aşağıdaki İncele/Onayla/Reddet/Yayınla aksiyonlarıyla değiştirilebilir.'),
                Forms\Components\Select::make('categories')
                    ->label('Kategoriler')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload(),
                Forms\Components\DateTimePicker::make('published_at')
                    ->label('Yayın Tarihi'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Başlık')
                    ->searchable(),
                Tables\Columns\TextColumn::make('author.name')
                    ->label('Yazar')
                    ->sortable(),
                Tables\Columns\TextColumn::make('magazineIssue.title')
                    ->label('Dergi Sayısı')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (ContentStatus $state) => $state->label()),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Yayın Tarihi')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Oluşturulma')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Durum')
                    ->options(collect(ContentStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('review')
                    ->label('İncele')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Makale Süper Admin onayına gönderilecek.')
                    ->visible(fn (Article $record): bool => auth()->user()->can('review', $record))
                    ->action(function (Article $record): void {
                        abort_unless(auth()->user()->can('review', $record), 403);

                        $record->update(['status' => ContentStatus::Incelemede]);
                        static::recordReview($record, 'incelemede');

                        Notification::make()->title('Makale Süper Admin onayına gönderildi')->success()->send();
                    }),

                Tables\Actions\Action::make('approve')
                    ->label('Onayla')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Article $record): bool => auth()->user()->can('approve', $record))
                    ->action(function (Article $record): void {
                        abort_unless(auth()->user()->can('approve', $record), 403);

                        $record->update(['status' => ContentStatus::Onaylandi]);
                        static::recordReview($record, 'onaylandi');
                        $record->author->notify(new ContentApproved($record));

                        Notification::make()->title('Makale onaylandı')->success()->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reddet')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Article $record): bool => auth()->user()->can('reject', $record))
                    // 2026-09-27, karar A: revizyon ile kalıcı ret ayrı — ContentReviewer (panelle aynı yol).
                    ->form([
                        Forms\Components\Radio::make('decision')
                            ->label('Karar')
                            ->options(['revizyon' => 'Revizyon iste (düzeltip tekrar gönderebilir)', 'ret' => 'Kalıcı olarak reddet (tekrar gönderilemez)'])
                            ->default('revizyon')
                            ->required(),
                        Forms\Components\Textarea::make('note')
                            ->label('Not / gerekçe')
                            ->required(),
                    ])
                    ->action(function (Article $record, array $data): void {
                        abort_unless(auth()->user()->can('reject', $record), 403);

                        if ($data['decision'] === 'ret') {
                            ContentReviewer::reject($record, auth()->user(), $data['note']);
                            Notification::make()->title('Makale kalıcı olarak reddedildi')->danger()->send();
                        } else {
                            ContentReviewer::requestRevision($record, auth()->user(), $data['note']);
                            Notification::make()->title('Makale revizyona gönderildi')->warning()->send();
                        }
                    }),

                Tables\Actions\Action::make('publish')
                    ->label('Yayınla')
                    ->icon('heroicon-o-globe-alt')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Article $record): bool => auth()->user()->can('publish', $record))
                    ->action(function (Article $record): void {
                        abort_unless(auth()->user()->can('publish', $record), 403);

                        ContentPublisher::publishArticle($record, auth()->user());

                        Notification::make()->title('Makale yayınlandı')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
