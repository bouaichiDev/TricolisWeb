@extends('mail.customer-portal.layout')

@section('content')
    <h1 style="font-size:20px;margin:0 0 16px;">Invitation au portail client</h1>
    <p>Bonjour,</p>
    <p>
        {{ $inviterName }} vous invite à rejoindre l'espace client
        <strong>{{ $customerName }}</strong> sur le portail {{ $organizationName }}.
    </p>
    <p style="margin:24px 0;">
        <a href="{{ $actionUrl }}" style="background:#2563eb;color:#ffffff;padding:12px 20px;border-radius:6px;text-decoration:none;">
            Activer mon compte
        </a>
    </p>
    <p style="font-size:13px;color:#4b5563;">Ce lien expire le {{ $expiresAt }}.</p>
@endsection
