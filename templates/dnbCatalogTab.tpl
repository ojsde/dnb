{if $dnbCatalogInfo}
    <div>
        <table style="border-collapse: collapse;">
            <caption>{translate key="plugins.importexport.dnb.dnbCatalogInfo.ISSNTableCaption"}</caption>
            <colgroup>
                {foreach $dnbCatalogInfo[0] as $key => $item}
                    <col class="grid-column" style="width: 17%;">
                {/foreach}
            </colgroup>
            <thead>
                <tr>
                    {foreach array_shift($dnbCatalogInfo) as $key => $item}
                        <th class="grid-column"  style="border: 1px solid black;padding: 8px;">
                            {$key}
                        </th>
                    {/foreach}
                </tr>
            </thead>
            <tbody>
                {foreach from=$dnbCatalogInfo item=line}
                    <tr>
                        {foreach from=$line item=$element}
                            <td class="dnb_log_entry" style="border: 1px solid black;padding: 8px;">{$element}</td>
                        {/foreach}
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
{/if}