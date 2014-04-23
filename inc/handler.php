<?php

if (!empty($_POST))
{
    print_r($_POST);
    setcookie('babel_lang', $_POST['babel_lang'], time()+(60*60*24*365), '/');
    header('Location: ' . $_POST['babel_return']);
    die();
}

header('Location: http://' . $_SERVER['SERVER_NAME']);
die();
