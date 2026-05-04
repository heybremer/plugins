{* MarkeFiltre — in index.tpl einbinden (Plugin setzt $MarkeFiltreWidgetTpl + auf der Startseite $mfBrandsJson).
   Beispiel: {include file='snippets/markefiltre_include.tpl'} (Datei ins Child-Template kopieren) oder direkt:
   {include file=$MarkeFiltreWidgetTpl}
*}
{if isset($MarkeFiltreWidgetTpl)}
    {include file=$MarkeFiltreWidgetTpl}
{/if}
