<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html>
    <head>
        <title>DBStat: <?=$title?></title>
        <script type="text/javascript" src="https://www.google.com/jsapi"></script>
        <script type="text/javascript" src="static/jquery-1.4.3.min.js"></script>
        <script type="text/javascript" src="static/scripts.js?<?=filemtime('../static/scripts.js')?>"></script>
        <link rel="stylesheet" href="static/styles.css?<?=filemtime('../static/styles.css')?>">
    </head>
    <body>

        <!-- Top background is positioned absolutely, because #header has no known height. -->
        <div id="header_background"></div>

        <!-- Pre-create graph DIV, because it is much faster in FF than appending it to document.body dynamically. -->
        <div id="graph" class="graph"></div>

        <div id="header">
            <div class="logo">
                <a href="index.php"><img src="static/logo.gif" width="108" height="43"/></a>
            </div>
            <div class="menu">
                <?php foreach ($menu as $url => $info) {
                    ob_start();
                    if (@$info['submenu']) {?>
                        <img src="static/triangle_down.gif" class="triangle_down"/>
                    <?php }?>
                    <a href="<?=$url?>" class="main_menu_link"><?=$info['title']?></a>
                    <?php $link = ob_get_clean(); ?>

                    <div class="item <?=$info['current']? 'current' : ''?> <?=$url == "logout.php"? "right" : ""?>">
                        <?php if (@$info['submenu']) {?>
                            <div class="inner real submenu">
                                <?=$link?>
                                <?php foreach ($info['submenu'] as $url => $subinfo) {?>
                                    <div class="submenu_item">
                                        <a href="<?=$url?>" class="<?=$subinfo['current']? 'current' : ''?>"><?=$subinfo['title']?><span class="count"><?=$subinfo['count']?></span></a>
                                    </div>
                                <?php }?>
                            </div>
                        <?php }?>
                        <div class="inner real">
                            <?=$link?>
                        </div>
                        <div class="inner shadow current">
                            <?=$link?>
                        </div>
                    </div>
                <?php }?>
            </div>
        </div>
        <script type="text/javascript">
        // Execute this before big page body is loaded.
        $('#header .triangle_down, #header .current .real a.main_menu_link').mousedown(function() {
            $(this).closest('.item').find('.submenu').fastToggle('block');
        });
        </script>

        <div id="text">
                <h1><?=@$titleHtml? unhtmlspecialchars($titleHtml) : $title?></h1>

                <?php foreach (getAndRemoveMessages() as $msg) {?>
                    <div class="message"><?=htmlspecialchars($msg)?></div>
                <?php }?>
