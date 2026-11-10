{{-- resources/views/pdf/non-conformity.blade.php — Formato F-CAL-24 lleno (dompdf) --}}
@php
    $d = fn ($date) => $date ? \Carbon\Carbon::parse($date)->locale('es')->isoFormat('D-MMM-YYYY') : '';
    $qty = $nc->quantity_involved !== null
        ? rtrim(rtrim(number_format((float) $nc->quantity_involved, 2), '0'), '.') . ' ' . ($nc->unit?->abbreviation ?? '')
        : '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $nc->folio }} — {{ $format['code'] }}</title>
<style>
    @page { margin: 28px 34px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 9.5px; color: #1f2937; }
    table { width: 100%; border-collapse: collapse; }
    td, th { border: 1px solid #1f2937; padding: 4px 6px; vertical-align: top; }
    .no-border td { border: none; }
    .head-name { text-align: center; font-weight: bold; }
    .section { background: #8B6F97; color: #fff; font-weight: bold; text-align: center; padding: 5px; margin-top: 10px; border: 1px solid #1f2937; }
    .section span { font-weight: normal; }
    .label { background: #E6D9E9; text-align: right; font-weight: bold; width: 42%; }
    .value { text-align: center; }
    .subhead { background: #E6D9E9; text-align: center; font-weight: bold; }
    .box { border: 1px solid #1f2937; padding: 6px 8px; }
    .rich p { margin: 0 0 4px 0; }
    .rich ul, .rich ol { margin: 2px 0 4px 18px; padding: 0; }
    .rich li { margin-bottom: 2px; }
    .rich h3, .rich h4 { font-size: 10.5px; margin: 6px 0 2px 0; }
    .small { font-size: 8.5px; }
    .center { text-align: center; }
    .ok { color: #0f766e; font-weight: bold; }
    .bad { color: #b91c1c; font-weight: bold; }
    .muted { color: #6b7280; }
    .photos { width: 100%; border-collapse: collapse; }
    .photos td { border: none; width: 50%; text-align: center; vertical-align: middle; padding: 4px; }
    .photos img { width: 240px; }
    .notice { font-size: 9px; text-align: center; padding: 5px; border: 1px solid #1f2937; background: #F5F0F6; }
    .stamp { margin-top: 4px; font-size: 8px; color: #6b7280; text-align: right; }
</style>
</head>
<body>

{{-- ── Encabezado del formato ── --}}
<table>
    <tr>
        <td rowspan="4" style="width: 30%; text-align: center; vertical-align: middle;">
            @if($logo)<img src="{{ $logo }}" style="max-height: 70px;">@endif
        </td>
        <td colspan="3" class="head-name">Nombre del documento</td>
    </tr>
    <tr><td colspan="3" class="center">{{ $format['name'] }}</td></tr>
    <tr>
        <td class="head-name">Fecha de emisión</td>
        <td class="head-name">Revisión</td>
        <td class="head-name">Código</td>
    </tr>
    <tr>
        <td class="center">{{ $format['issued'] }}</td>
        <td class="center">{{ $format['revision'] }}</td>
        <td class="center">{{ $format['code'] }}</td>
    </tr>
</table>

{{-- ── PRIMERA SECCIÓN ── --}}
<div class="section">PRIMERA SECCIÓN <span>(A llenar por DASAVENA GOURMET S.A. de C.V)</span></div>
<table style="margin-top: 6px;">
    <tr><td class="label">Fecha de desviación</td><td class="value">{{ $d($nc->deviation_date) }}</td></tr>
    <tr><td class="label">Folio</td><td class="value">{{ $nc->folio }}</td></tr>
</table>
<table style="margin-top: 6px;">
    <tr><td class="label">Proveedor</td><td class="value">{{ $nc->provider?->business_name }}</td></tr>
    <tr><td class="label">Producto</td><td class="value">{{ $nc->productService?->name }}</td></tr>
    <tr><td class="label">Lote</td><td class="value">{{ $nc->lot }}</td></tr>
    <tr><td class="label">Fecha de recepción</td><td class="value">{{ $d($nc->reception_date) }}</td></tr>
    <tr><td class="label">Cantidad involucrada</td><td class="value">{{ $qty }}</td></tr>
    <tr><td class="label">Tipo de desviación</td><td class="value">{{ $nc->type?->name }}</td></tr>
</table>
<table style="margin-top: 6px;">
    <tr><td class="subhead">Descripción de la No Conformidad</td></tr>
    <tr><td class="rich">{!! $descriptionHtml !!}</td></tr>
    @if($photos->isNotEmpty())
    <tr><td style="padding: 6px;">
        {{-- Fotos en su propia fila y en cuadrícula de 2 columnas: dompdf no acomoda bien imágenes en línea junto a texto --}}
        <table class="photos">
            @foreach($photos->chunk(2) as $row)
            <tr>
                @foreach($row as $src)
                    <td><img src="{{ $src }}"></td>
                @endforeach
                @if($row->count() === 1)<td></td>@endif
            </tr>
            @endforeach
        </table>
    </td></tr>
    @endif
</table>

{{-- ── SEGUNDA SECCIÓN ── --}}
<div class="section">SEGUNDA SECCIÓN <span>(A llenar por el proveedor)</span></div>
<div class="notice">
    El límite máximo de tiempo para contestar la No Conformidad es de {{ $responseDays }} días. Una vez enviadas las acciones
    correctivas, cuentan con otros {{ $evidenceDays }} días para enviar las evidencias.
    <strong>El hacer caso omiso a la No Conformidad influirá en su calificación como proveedor.</strong>
</div>

@if($response)
    @unless($responseIsFinal)
        <p class="small bad center" style="margin: 4px 0;">Respuesta (vuelta {{ $response->round }}) pendiente de aceptación por DASAVENA</p>
    @endunless
    <table style="margin-top: 6px;">
        <tr><td class="label">Líder del equipo</td><td class="value">{{ $response->team_leader }}</td></tr>
        <tr><td class="label">Área</td><td class="value">{{ $response->area }}</td></tr>
    </table>
    <table style="margin-top: 6px;">
        <tr><td class="subhead">Identificación de la causa raíz <span style="font-weight: normal;">(Puede utilizar la metodología que prefiera)</span></td></tr>
        <tr><td class="subhead" style="background: #F5F0F6;">Descripción de la causa raíz</td></tr>
        <tr><td class="rich">{!! $rootCauseHtml !!}</td></tr>
    </table>
    <table style="margin-top: 6px;">
        <tr><td colspan="5" class="subhead">Acciones correctivas</td></tr>
        <tr>
            <th class="subhead" style="width: 33%;">Acción</th>
            <th class="subhead" style="width: 14%;">Responsable</th>
            <th class="subhead" style="width: 13%;">Fecha compromiso</th>
            <th class="subhead" style="width: 13%;">Fecha de realización</th>
            <th class="subhead" style="width: 27%;">Evidencia a entregar</th>
        </tr>
        @foreach($response->actions as $a)
        <tr>
            <td>{{ $a->action }}</td>
            <td class="center">{{ $a->responsible }}</td>
            <td class="center">{{ $d($a->commitment_date) }}</td>
            <td class="center">{{ $d($a->completion_date) }}</td>
            <td>{{ $a->evidence_to_deliver }}</td>
        </tr>
        @endforeach
    </table>
@else
    <div class="box center muted" style="margin-top: 6px;">El proveedor aún no ha enviado su análisis de causa raíz y acciones correctivas.</div>
@endif

{{-- ── TERCERA SECCIÓN ── --}}
<div class="section">TERCERA SECCIÓN <span>(A llenar por DASAVENA GOURMET S.A. de C.V)</span></div>
@if($response && $responseIsFinal)
    <table style="margin-top: 6px;">
        <tr>
            <th class="subhead" style="width: 34%;">Evidencias</th>
            <th class="subhead" style="width: 34%;">Evidencia enviada por proveedor</th>
            <th class="subhead" style="width: 16%;">Fecha de envío</th>
            <th class="subhead" style="width: 16%;">Cumple / No cumple</th>
        </tr>
        @foreach($response->actions as $a)
            @php $ev = $a->evidences->first(); @endphp
            <tr>
                <td>{{ $a->evidence_to_deliver }}</td>
                <td>{{ $ev?->original_name }}@if($ev && $ev->attempt > 1) <span class="small muted">(intento {{ $ev->attempt }})</span>@endif</td>
                <td class="center">{{ $ev ? $d($ev->submitted_at) : '' }}</td>
                <td class="center">
                    @if(!$ev) <span class="muted">Pendiente</span>
                    @elseif($ev->review_status === 'accepted') <span class="ok">Cumple</span>
                    @elseif($ev->review_status === 'rejected') <span class="bad">No cumple</span>
                    @else <span class="muted">En revisión</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
@else
    <div class="box center muted" style="margin-top: 6px;">Las evidencias se registran una vez aceptado el plan de acción.</div>
@endif

<table style="margin-top: 8px;">
    <tr>
        <td class="subhead" style="width: 70%;">Firma electrónica del emisor en señal de cierre</td>
        <td class="subhead">Fecha</td>
    </tr>
    <tr>
        <td class="center" style="height: 26px; vertical-align: middle;">
            @if($nc->closed_at) <strong>{{ $nc->closedBy?->name }}</strong> @else <span class="muted">Sin cerrar</span> @endif
        </td>
        <td class="center" style="vertical-align: middle;">{{ $nc->closed_at ? $d($nc->closed_at) : '' }}</td>
    </tr>
    @if($nc->closing_comment)
    <tr><td colspan="2"><strong>Comentario de cierre:</strong> {{ $nc->closing_comment }}</td></tr>
    @endif
</table>

<p class="stamp">Generado desde el SGP el {{ now()->locale('es')->isoFormat('D-MMM-YYYY HH:mm') }} · Estado: {{ $nc->display_status }}</p>

</body>
</html>