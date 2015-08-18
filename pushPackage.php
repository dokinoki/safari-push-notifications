    <?php
    
    require_once('pushPackageClass.php');
    
    //Create a push package using the class and the userID
    $objPushClass = new pushPackageClass($_COOKIE['USER_ID']);
    
    //Generate a ZIP file and serve it
    $objPushClass->serve_push_package();
