@extends('mail.customer-portal.layout')

@section('content')
    <h1 style="font-size:20px;margin:0 0 16px;">Réinitialisation du mot de passe</h1>
    <p>Bonjour {{ $firstName }},</p>
    <p>Vous avez demandé à réinitialiser le mot de passe de votre accès au portail client.</p>
    <p style="margin:24px 0;">
        <a href="{{ $actionUrl }}" style="background:#2563eb;color:#ffffff;padding:12px 20px;border-radius:6px;text-decoration:none;">
            Choisir un nouveau mot de passe
        </a>
    </p>
    <p style="font-size:13px;color:#4b5563;">Ce lien expire dans {{ $expiresInMinutes }} minutes.</p>
@endsection
