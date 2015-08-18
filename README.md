# safari-push-notifications
Classes for creating apple push notifications in PHP

## How to create push packages for your website

1 - Create push folder in server <br>
2 - Give permisions to folder (chown -R apache push) <br>
3 - Create icon.iconset folder inside of push folder <br>
4 - Add the icons to the icon.iconset folder according to apple´s guidelines  <br>
5 - Add p12 keyfile to push folder <br>
6 - Create .htaccess rule to create REST endpoints <br>
7 - Create pushClass and the methods register and deregister to save stuff to db

## Structure

- package.php --> serves the package to safari
- register.php --> registers the user's notification consent with the device ID
- log.php --> sends the errors to apache's error log
