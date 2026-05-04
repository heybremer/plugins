{assign var=posts value=$bgPosts|default:[]}
{if is_array($posts) && $posts|@count > 0}
<div class="container opc-Container">
    <section class="bg-wrap" data-bloggie="1">
        <div class="bg-head">
            <h2 class="bg-title">{$bgTitle|default:'Blog'|escape:'html'}</h2>
        </div>
        <div class="bg-grid">
            {foreach $posts as $p}
                <a class="bg-card" href="{$p.detailUrl|default:'#'|escape:'html'}">
                    <h3 class="bg-card-title">{$p.title|escape:'html'}</h3>
                    {if $p.excerpt|default:'' !== ''}<p class="bg-excerpt">{$p.excerpt|escape:'html'}</p>{/if}
                </a>
            {/foreach}
        </div>
        {if $bgAllNewsUrl|default:'' !== ''}
            <div class="bg-footer">
                <a class="bg-all" href="{$bgAllNewsUrl|escape:'html'}">Weitere Blogbeiträge</a>
            </div>
        {/if}
    </section>
</div>
{/if}

