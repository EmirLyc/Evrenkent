{{-- Filament yedek panel notu (AdminPanelProvider). Filament'in kendi CSS'i yüklü, bizim Tailwind sınıflarımız değil — satır içi stil. --}}
<div style="margin: 1rem 0; padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #F6CB96; background: #FDF3E7; color: #9E540A; font-size: 0.875rem; line-height: 1.4;">
    Bu <strong>yedek (acil durum) panelidir</strong>. Günlük işler için
    <a href="{{ route('panel.adminpanel.index') }}" style="text-decoration: underline; font-weight: 600;">Süper Admin paneline</a> gidin —
    belge, video, dergi ataması, indirimler ve premium ayarları yalnızca orada.
</div>
