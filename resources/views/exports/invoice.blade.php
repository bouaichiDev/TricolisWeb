{{--
    Mise en page de la facture PDF.

    Volontairement sobre et sans image : dompdf va chercher chaque ressource
    externe au moment du rendu, et un logo hebergé ailleurs ferait dependre la
    facture d'un serveur qui peut etre lent ou absent au mauvais moment.

    Les montants arrivent deja formates en chaines depuis le DTO : les
    reformater ici les arrondirait une seconde fois.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $invoice->invoiceNumber }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .muted { color: #666; }
        .head { margin-bottom: 16px; }
        .head td { padding: 1px 0; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.lines th { background: #f2f2f2; text-align: left; padding: 5px; border-bottom: 1px solid #ccc; }
        table.lines td { padding: 5px; border-bottom: 1px solid #eee; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals { width: 40%; margin-left: 60%; margin-top: 12px; border-collapse: collapse; }
        .totals td { padding: 3px 5px; }
        .totals tr.grand td { border-top: 1px solid #333; font-weight: bold; font-size: 12px; }
        .notes { margin-top: 24px; font-size: 9px; color: #666; }
    </style>
</head>
<body>
<div class="head">
    <h1>{{ $title }} {{ $invoice->invoiceNumber }}</h1>
    <table>
        <tr><td class="muted">Date&nbsp;:&nbsp;</td><td>{{ $invoice->invoiceDate }}</td></tr>
        @if ($invoice->periodFrom || $invoice->periodTo)
            <tr>
                <td class="muted">Période&nbsp;:&nbsp;</td>
                <td>{{ $invoice->periodFrom }} — {{ $invoice->periodTo }}</td>
            </tr>
        @endif
        @if ($invoice->externalReference)
            <tr><td class="muted">Référence&nbsp;:&nbsp;</td><td>{{ $invoice->externalReference }}</td></tr>
        @endif
    </table>
</div>

<table class="lines">
    <thead>
    <tr>
        <th>#</th>
        <th>Prestation</th>
        <th>Livraison</th>
        <th class="num">Qté</th>
        <th class="num">P.U.</th>
        <th class="num">TVA</th>
        <th class="num">Total HT</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($invoice->lines as $line)
        <tr>
            <td>{{ $line['lineNumber'] ?? '' }}</td>
            <td>
                {{ $line['description'] ?? $line['serviceCode'] ?? '' }}
                @if (! empty($line['customerOrderReference']))
                    <br><span class="muted">{{ $line['customerOrderReference'] }}</span>
                @endif
            </td>
            <td class="muted">
                @if (! empty($line['address']))
                    {{ $line['address']['name'] ?? '' }}
                    @if (! empty($line['address']['city']))
                        <br>{{ $line['address']['postalCode'] ?? '' }} {{ $line['address']['city'] }}
                    @endif
                @endif
            </td>
            <td class="num">{{ $line['quantity'] ?? '' }}</td>
            <td class="num">{{ $line['unitPrice'] ?? '' }}</td>
            <td class="num">{{ $line['taxRate'] ?? '' }}</td>
            <td class="num">{{ $line['totalExcludingTax'] ?? '' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Total HT</td><td class="num">{{ $invoice->subtotal }} {{ $invoice->currencyCode }}</td></tr>
    <tr><td>TVA</td><td class="num">{{ $invoice->taxTotal }} {{ $invoice->currencyCode }}</td></tr>
    <tr class="grand"><td>Total TTC</td><td class="num">{{ $invoice->total }} {{ $invoice->currencyCode }}</td></tr>
</table>

@if ($invoice->remark || $footnotes !== [])
    <div class="notes">
        @if ($invoice->remark)<p>{{ $invoice->remark }}</p>@endif
        @foreach ($footnotes as $label => $value)
            <div>{{ $label }} : {{ $value }}</div>
        @endforeach
    </div>
@endif
</body>
</html>
