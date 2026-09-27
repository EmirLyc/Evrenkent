<?php

namespace App\Filament\Resources;

use App\Enums\ContentStatus;
use App\Filament\Concerns\RecordsContentReview;
use App\Filament\Resources\MagazineIssueResource\Pages;
use App\Filament\Resources\MagazineIssueResource\RelationManagers;
use App\Models\MagazineIssue;
use App\Notifications\ContentApproved;
use App\Support\ContentPublisher;
use App\Notifications\ContentRevisionRequested;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MagazineIssueResource extends Resource
{
    use RecordsContentReview;

    protected static ?string $model = MagazineIssue::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'Dergi Yönetimi';

    protected static ?string $modelLabel = 'Dergi Sayısı';

    protected static ?string $pluralModelLabel = 'Dergi Sayıları';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Faz E: sayı bir dergiye bağlı. Kendi panelimizde editör dergiden türetiliyor;
                // bu yedek arayüzde editör hâlâ ayrıca seçiliyor (derginin editörüyle aynı olmalı).
                Forms\Components\Select::make('magazine_id')
                    ->label('Dergi')
                    ->relationship('magazine', 'name')
                    ->required(),
                Forms\Components\Select::make('editor_id')
                    ->label('Dergi Editörü')
                    ->relationship('editor', 'name')
                    ->required(),
                Forms\Components\TextInput::make('title')
                    ->label('Başlık')
                    ->required(),
                Forms\Components\TextInput::make('issue_number')
                    ->label('Sayı No')
                    ->required()
                    ->numeric(),
                Forms\Components\FileUpload::make('cover_image')
                    ->label('Kapak Görseli')
                    ->image()
                    ->disk(config('filesystems.covers_disk'))
                    ->directory('covers/magazine-issues')
                    ->maxSize(5120),
                Forms\Components\Textarea::make('editor_note')
                    ->label('Editör Yazısı')
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
                        : 'Durum sadece aşağıdaki Yayına Gönder/Onayla/Reddet/Yayınla aksiyonlarıyla değiştirilebilir.'),
                Forms\Components\DatePicker::make('publish_date')
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
                Tables\Columns\TextColumn::make('issue_number')
                    ->label('Sayı No')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('editor.name')
                    ->label('Editör')
                    ->sortable(),
                Tables\Columns\ImageColumn::make('cover_image')
                    ->label('Kapak')
                    ->disk(config('filesystems.covers_disk')),
                Tables\Columns\TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (ContentStatus $state) => $state->label()),
                Tables\Columns\TextColumn::make('publish_date')
                    ->label('Yayın Tarihi')
                    ->date()
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

                Tables\Actions\Action::make('submit')
                    ->label('Yayına Gönder')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Bu sayı Süper Admin onayına gönderilecek.')
                    ->visible(fn (MagazineIssue $record): bool => auth()->user()->can('submit', $record))
                    ->action(function (MagazineIssue $record): void {
                        abort_unless(auth()->user()->can('submit', $record), 403);

                        $record->update(['status' => ContentStatus::Gonderildi]);
                        static::recordReview($record, 'gonderildi');

                        Notification::make()->title('Sayı Süper Admin onayına gönderildi')->success()->send();
                    }),

                Tables\Actions\Action::make('approve')
                    ->label('Onayla')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (MagazineIssue $record): bool => auth()->user()->can('approve', $record))
                    ->action(function (MagazineIssue $record): void {
                        abort_unless(auth()->user()->can('approve', $record), 403);

                        $record->update(['status' => ContentStatus::Onaylandi]);
                        static::recordReview($record, 'onaylandi');
                        $record->editor->notify(new ContentApproved($record));

                        Notification::make()->title('Sayı onaylandı')->success()->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reddet')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (MagazineIssue $record): bool => auth()->user()->can('reject', $record))
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('Revizyon Notu')
                            ->required(),
                    ])
                    ->action(function (MagazineIssue $record, array $data): void {
                        abort_unless(auth()->user()->can('reject', $record), 403);

                        $record->update(['status' => ContentStatus::RevizyonIstendi]);
                        static::recordReview($record, 'revizyon_istendi', $data['note']);
                        $record->editor->notify(new ContentRevisionRequested($record, $data['note']));

                        Notification::make()->title('Sayı revizyona gönderildi (Dergi Editörüne döndü)')->warning()->send();
                    }),

                Tables\Actions\Action::make('publish')
                    ->label('Yayınla')
                    ->icon('heroicon-o-globe-alt')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (MagazineIssue $record): bool => auth()->user()->can('publish', $record))
                    ->action(function (MagazineIssue $record): void {
                        abort_unless(auth()->user()->can('publish', $record), 403);

                        // Kendi panelimiz ve zamanlayıcıyla aynı yol: sayının onaylı makaleleri de yayına girer.
                        ContentPublisher::publishIssue($record, auth()->user());

                        Notification::make()->title('Sayı, onaylı makaleleriyle birlikte yayınlandı')->success()->send();
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
            'index' => Pages\ListMagazineIssues::route('/'),
            'create' => Pages\CreateMagazineIssue::route('/create'),
            'edit' => Pages\EditMagazineIssue::route('/{record}/edit'),
        ];
    }
}
