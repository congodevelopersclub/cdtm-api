<x-mail::message>
# Bienvenue, {{ $name }} !

Merci de vous être inscrit sur la plateforme CDTM. Nous sommes ravis de vous compter parmi nous.

<x-mail::button :url="config('services.frontend_url')">
Accéder à mon espace
</x-mail::button>

Cordialement,<br>
L'équipe {{ config('app.name') }}
</x-mail::message>
