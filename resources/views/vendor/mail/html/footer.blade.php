{{-- Pied officiel : logo et mention de la Fondation Tunivisions. --}}
<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center" style="padding: 32px 32px 40px;">
<a href="{{ \App\Support\Domains::url('site') }}" style="display: inline-block; text-decoration: none;">
<img src="{{ \App\Support\Domains::url('site', '/logoFondation.png') }}" alt="Tunivisions Foundation" width="160" height="44" style="display: block; width: 160px; height: 44px; border: 0; margin: 0 auto;">
</a>
<p style="margin: 14px 0 4px; font-size: 13px; font-weight: bold; color: #52525b; text-align: center;">Fondation Tunivisions</p>
{{ Illuminate\Mail\Markdown::parse($slot) }}
</td>
</tr>
</table>
</td>
</tr>
