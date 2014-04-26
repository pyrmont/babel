<?php
    $form_url = '/Storage/plugins/hd-babel/inc/handler.php';
    $return_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>

<div id="babel_lang_controls">
    <form action="<?php echo $form_url ?>" method="post">
        <select name="babel_lang">
            <?php foreach ($langs as $lang) { ?>
            <option value="<?php echo $lang['code'] ?>" <?php if ($lang['code'] === $current['code']) { echo 'SELECTED'; } ?>><?php echo $lang['name'] ?></option>
            <?php } ?>
        </select>
        <input name="babel_return" type="hidden" value="<?php echo $return_url ?>">
        <input type="submit">
    </form>
</div>