<?php
    //Logear los errores de pushService
    if(!empty($_POST)) error_log(json_encode($_POST));