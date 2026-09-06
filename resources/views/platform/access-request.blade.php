Une nouvelle demande d'accès a été déposée depuis l'écran de connexion.

Société    : {{ $request->company_name }}
Contact    : {{ $request->contact_name }}
E-mail     : {{ $request->email }}
Téléphone  : {{ $request->phone }}
@if ($request->message)

Message :
{{ $request->message }}
@endif

Acceptez-la ou refusez-la depuis Tricolis, page « Demandes d'accès ».
Tant qu'elle n'est pas acceptée, aucun compte ni aucune organisation n'existe.
