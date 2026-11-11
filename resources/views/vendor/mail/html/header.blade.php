@props(['url'])
{{-- En-tête officiel : logo T.Link sur bandeau blanc, filet rouge de la Fondation. --}}
<tr>
<td class="header" align="center" style="background-color: #ffffff; border-top: 5px solid #d00031; border-bottom: 1px solid #ece7e9; padding: 24px 0;">
<a href="{{ \App\Support\Domains::url('tlink') }}" style="display: inline-block;">
<img src="{{ \App\Support\Domains::url('tlink', '/tlink-logo.png') }}" alt="T.Link" width="119" height="45" style="display: block; width: 119px; height: 45px; border: 0;">
</a>
</td>
</tr>
