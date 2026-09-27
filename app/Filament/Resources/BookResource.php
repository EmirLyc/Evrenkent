<?php

namespace App\Filament\Resources;

use App\Enums\ContentStatus;
use App\Filament\Concerns\RecordsContentReview;
use App\Filament\Resources\BookResource\Pages;
use App\Models\Book;
use App\Notifications\ContentApproved;
use App\Notifications\ContentPublished;
use App\Notifications\ContentRevisionRequested;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BookResource extends Resource
{
    use RecordsContentReview;

    protected static ?string $model = Book::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Yayın Yönetimi';

    protected static ?string $modelLabel = 'Kitap';

    protected static ?string $pluralModelLabel = 'Kitaplar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('author_id')
                    ->label('Yazar')
                    ->relationship('author', 'name')
                    ->required(),
                Forms\Components\TextInput::make('title')
                    ->label('Başlık')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $state, Forms\Set $set) => $set('slug', str($state)->slug())),
                Forms\Components\TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\Textarea::make('description')
                    ->label('Açıklama')
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('cover_image')
                    ->label('Kapak Görseli')
                    ->image()
                    ->disk(config('filesystems.covers_disk'))
                    ->directory('covers/books')
                    ->maxSize(5120),
                Forms\Components\TextInput::make('price')
                    ->label('Fiyat')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('₺'),
                Forms\Components\TextInput::make('discount_price')
                    ->label('İndirimli Fiyat')
                    ->numeric()
                    ->prefix('₺')
                    ->lt('price')
                    ->helperText('Doluysa anasayfada/kataloğun "Fırsatlar" rafında gösterilir. Boş bırakılırsa normal fiyatla satılır.'),
                Forms\Components\Toggle::make('is_editors_pick')
                    ->label('Editörün Seçkisi')
                    ->helperText('Aktifse anasayfada/kataloğun "Editörün Seçkisi" rafında gösterilir.'),
                Forms\Components\Select::make('status')
                    ->label('Durum')
                    ->options(collect(ContentStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->default(ContentStatus::Taslak->value)
                    ->required()
                    ->disabled(fn (string $operation): bool => $operation !== 'create')
                    ->dehydrated(fn (string $operation): bool => $operation === 'create')
                    ->helperText(fn (string $operation): ?string => $operation === 'create'
                        ? null
                        : 'Durum sadece aşağıdaki Onayla/Reddet/Yayınla aksiyonlarıyla değiştirilebilir.'),
                Forms\Components\Select::make('categories')
                    ->label('Kategoriler')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload(),
                Forms\Components\DateTimePicker::make('published_at')
                    ->label('Yayın Tarihi'),

                Forms\Components\Section::make('Değerlendirme')
                    ->description('Gerçek bir yorum/puanlama sistemi kurulana kadar özet değerler burada elle girilir.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('average_rating')
                            ->label('Ortalama Puan')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(5)
                            ->step(0.1),
                        Forms\Components\TextInput::make('review_count')
                            ->label('Değerlendirme Sayısı')
                            ->numeric()
                            ->minValue(0),
                    ]),

                Forms\Components\Section::make('İçerik İstatistikleri')
                    ->description('Satın alma sayfasında sadece doldurulan alanlar gösterilir — kitabı yükleyen yazar da kendi panelinden bunları girebilir.')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('page_count')->label('Sayfa')->numeric()->minValue(0),
                        Forms\Components\TextInput::make('document_count')->label('Belge')->numeric()->minValue(0),
                        Forms\Components\TextInput::make('video_count')->label('Video')->numeric()->minValue(0),
                        Forms\Components\TextInput::make('map_count')->label('Harita')->numeric()->minValue(0),
                        Forms\Components\TextInput::make('author_note_count')->label('Yazar Notu')->numeric()->minValue(0),
                        Forms\Components\TextInput::make('source_count')->label('Kaynak')->numeric()->minValue(0),
                    ]),
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
                Tables\Columns\ImageColumn::make('cover_image')
                    ->label('Kapak')
                    ->disk(config('filesystems.covers_disk')),
                Tables\Columns\TextColumn::make('price')
                    ->label('Fiyat')
                    ->money('TRY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount_price')
                    ->label('İndirimli Fiyat')
                    ->money('TRY')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_editors_pick')
                    ->label('Editörün Seçkisi')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (ContentStatus $state) => $state->label()),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Yayın Tarihi')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('scheduled_publish_at')
                    ->label('Planlanan Yayın Tarihi')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
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

                Tables\Actions\Action::make('approve')
                    ->label('Onayla')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Book $record): bool => auth()->user()->can('approve', $record))
                    ->form([
                        // Kendi panelimizdeki onay ekranıyla aynı kural (bkz. ContentApprovalController::approveBook,
                        // Concerns\ResolvesBookPrice): fiyatı Süper Admin belirler, 0 TL ancak "Ücretsiz" işaretiyle.
                        Forms\Components\TextInput::make('price')
                            ->label('Satış Fiyatı')
                            ->numeric()
                            ->prefix('₺')
                            ->default(fn (Book $record) => (float) $record->price > 0 ? $record->price : null)
                            ->required(fn (Forms\Get $get): bool => ! $get('is_free'))
                            ->minValue(fn (Forms\Get $get): ?float => $get('is_free') ? null : 0.01)
                            ->disabled(fn (Forms\Get $get): bool => (bool) $get('is_free')),
                        Forms\Components\Toggle::make('is_free')
                            ->label('Bu kitap ücretsiz')
                            ->live(),
                        Forms\Components\DateTimePicker::make('scheduled_publish_at')
                            ->label('Planlanan Yayın Tarihi')
                            ->default(fn (Book $record) => $record->scheduled_publish_at)
                            ->helperText('Yazarın önerdiği tarih varsa önceden dolu gelir, değiştirebilir veya boş bırakabilirsiniz. Boşsa kitap "Yayınla" aksiyonuyla elle yayınlanana kadar sadece "Onaylandı" durumunda kalır. Doluysa "Yakında Çıkacaklar" rafında teaser olarak görünür ve tarihi gelince otomatik yayınlanır.'),
                    ])
                    ->action(function (Book $record, array $data): void {
                        abort_unless(auth()->user()->can('approve', $record), 403);

                        $isFree = (bool) ($data['is_free'] ?? false);

                        $record->update(array_merge([
                            'status' => ContentStatus::Onaylandi,
                            'scheduled_publish_at' => $data['scheduled_publish_at'] ?? null,
                            'price' => $isFree ? 0 : $data['price'],
                        ], $isFree ? ['discount_price' => null, 'discount_ends_at' => null] : []));
                        static::recordReview($record, 'onaylandi');
                        $record->author->notify(new ContentApproved($record));

                        Notification::make()->title('Kitap onaylandı')->success()->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reddet')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Book $record): bool => auth()->user()->can('reject', $record))
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('Revizyon Notu')
                            ->required(),
                    ])
                    ->action(function (Book $record, array $data): void {
                        abort_unless(auth()->user()->can('reject', $record), 403);

                        $record->update(['status' => ContentStatus::RevizyonIstendi]);
                        static::recordReview($record, 'revizyon_istendi', $data['note']);
                        $record->author->notify(new ContentRevisionRequested($record, $data['note']));

                        Notification::make()->title('Kitap revizyona gönderildi')->warning()->send();
                    }),

                Tables\Actions\Action::make('publish')
                    ->label('Yayınla')
                    ->icon('heroicon-o-globe-alt')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Book $record): bool => auth()->user()->can('publish', $record))
                    ->action(function (Book $record): void {
                        abort_unless(auth()->user()->can('publish', $record), 403);

                        $record->update(['status' => ContentStatus::Yayinda, 'published_at' => now()]);
                        static::recordReview($record, 'yayinda');
                        $record->author->notify(new ContentPublished($record));

                        Notification::make()->title('Kitap yayınlandı')->success()->send();
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
            'index' => Pages\ListBooks::route('/'),
            'create' => Pages\CreateBook::route('/create'),
            'edit' => Pages\EditBook::route('/{record}/edit'),
        ];
    }
}
