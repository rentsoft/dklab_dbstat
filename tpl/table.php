<?php
$COLORS = array("holiday" => "red", "incomplete" => "#BBBBBB");
$hideCommonPrefix = getSetting('hide_common_prefix', 0);
?>

<table cellpadding="3" cellspacing="1" border="0" bgcolor="#CCCCCC">
    <thead bgcolor="#F5F5F5">
        <tr align="center" valign="top">
            <td width="1" align="middle" valign="middle"><b>#</b></td>
            <td width="1" align="left" valign="middle"><b>Name</b></td>
            <td width="1" valign="middle"><b>TOT</b></td>
            <td width="1" valign="middle"><b>AVG</b></td>
            <td><br/></td>
            <?php foreach ($table['captions'] as $interval) {
                $styles = array();
                if (!$interval['is_complete']) $styles[] = "color:{$COLORS['incomplete']}";
                else if ($interval['is_holiday']) $styles[] = "color:{$COLORS['holiday']}";
                ?>
                <td width="1"<?=$styles? ' style="' . join(";", $styles) . '"' : ''?>>
                    <?=nl2br($interval['caption'])?>
                </td>
            <?php }?>
        </tr>
    </thead>
    <tbody class="table_data">
        <?php
        $hasArchived = 0;
        $zebra = array("#FFFFFF", !isCgi()? "FAFAFA" : "#FFFFFF");
        $i = -1;
        $n = 0;
        $caption = '';
        foreach ($table['groups'] as $groupName => $group) {
            $i++;
            foreach ($group as $rowName => $row) {
                $n++;
                $prevCaption = $caption;
                $caption = (strlen($groupName)? $groupName . "/" : "") . (strlen($rowName)? $rowName : "&lt;none&gt;");
                ?>
                <tr
                    id="<?=$row['item_id']?>"
                    <?=$row['archived']? 'style="display:none" class="archived id' . $row['item_id'] . '"' : ''?>
                    <?=$row['relative_name']? 'title="Relative to ' . $row['relative_name'] . '"' : ""?>
                    align="center" valign="middle" bgcolor="<?=$zebra[$n % 2]?>">
                    <td><font color="#AAA"><?=$n?></font></td>
                    <td align="left">
                        <?php if (strlen($row['comment'])) {?>
                            <div><span style="font-size:70%; color:#AAA; <?=isCgi()? 'padding-left:15px' : ''?>"><?=nl2br(str_replace('\\n', "\n", $row['comment']))?></span></div>
                        <?php }?>
                        <div style="white-space:nowrap">
                            <?php if (isCgi()) {?>
                                <a href="<?=$base?>item.php?clone=<?=$row['item_id']?>&retpath=<?=urlencode($_SERVER['REQUEST_URI'])?>" title="Clone this item"><img src="<?=$base?>static/clone.gif" width="10" height="10" border="0" style="margin-right:5px"/></a>
                            <?php }?>
                            <b><a style="text-decoration:none" href="<?=$base?>item.php?id=<?=$row['item_id']?><?=isCgi()?'&retpath='.urlencode($_SERVER['REQUEST_URI']):''?>"><?=$hideCommonPrefix? makeCommonPrefixTransparent($prevCaption, $caption, '/', 'color:#AAA') : $caption?></a></b>&nbsp;
                        </div>
                    </td>
                    <td><?=$row['total']?></td>
                    <td><?=$row['average']?></td>
                    <td width="1" class="check">
                        <?php if (isCgi()) {?>
                            <input type="checkbox" class="chk" name="chk[<?=$row['item_id']?>]" value="1"/>
                        <?php }?>
                    </td>
                    <?php foreach ($table['captions'] as $uniq => $interval) {
                        if (is_array($cell = $row["cells"][$uniq])) {
                            $styles = array();
                            if (strlen($cell['percent'])) $styles[] = "line-height:70%";
                            if (!$cell['is_complete']) $styles[] = "color:{$COLORS['incomplete']}";
                            else if ($interval['is_holiday']) $styles[] = "color:{$COLORS['holiday']}";
                         ?>
                            <td
                                <?=$styles? 'style="' . join(";", $styles) . '"' : ''?>
                                <?=!$cell['is_complete']? 'class="incomplete" title="Incomplete; till ' . date("Y-m-d H:i:s", $cell['created']) . ' only"' : ""?>
                                value="<?=extractNumeric($cell['value'])?>">
                                <?=$cell['value']?>
                                <?php if (strlen($cell['percent'])) {?>
                                    <font size="-2" color="#A0A0A0"><br/><?=$cell['percent']?>%</font>
                                <?php }?>
                            </td>
                        <?php } else {?>
                            <td class="incomplete"><br/></td>
                        <?php }
                    }?>
                </tr>
            <?php }
            $archivedGroup = 1; 
            foreach ($group as $row) if (!$row['archived']) $archivedGroup = 0; else $hasArchived = 1;
            if ($i < count($table['groups']) - 1) {?>
                <tr
                    <?=$archivedGroup? 'style="display:none" class="archived"' : ''?>
                    align="center" valign="middle" bgcolor="#FFFFFF">
                    <?php for ($n = 0; $n < count($table['captions']) + 4; $n++) {?>
                        <td height="10"><span></span></td>
                    <?php }?>
                </tr>
            <?php }
        }?>
    </tbody>
</table>

