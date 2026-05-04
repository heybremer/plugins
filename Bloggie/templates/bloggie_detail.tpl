{assign var=post value=$bgPost|default:null}
{if $post !== null}
<div class="container opc-Container">
    <section class="bg-wrap bg-wrap--detail" data-bloggie="1">
        <div class="bg-head">
            <h2 class="bg-title">{$bgTitle|default:'Blog'|escape:'html'}</h2>
        </div>

        <article class="bg-detail">
            <h3 class="bg-detail-title">{$post.title|escape:'html'}</h3>
            {if $post.excerpt|default:'' !== ''}<p class="bg-detail-excerpt">{$post.excerpt|escape:'html'}</p>{/if}
            {if $post.date|default:'' !== ''}<p class="bg-detail-date">{$post.date|escape:'html'}</p>{/if}

            <div class="bg-detail-body">{$post.body nofilter}</div>

            <div class="bg-detail-footer">
                <a class="bg-detail-product" href="{$post.productUrl|default:'#'|escape:'html'}">Zum Produkt</a>
            </div>
        </article>
    </section>
</div>
{/if}

