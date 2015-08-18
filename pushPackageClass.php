<?php

/**
 * Class createPushPackage
 *
 * This class creates a valid push package.
 * This script assumes that the website.json file and iconset already exist.
 * This script creates a manifest and signature, zips the folder, and returns the push package.
 *
 * @author Guillermo Barba
 * @version 1.0
 * @since 27/01/2015
 * @link https://developer.apple.com/library/mac/documentation/NetworkingInternet/Conceptual/NotificationProgrammingGuideForWebsites/PushNotifications/PushNotifications.html#//apple_ref/doc/uid/TP40013225-CH3-SW1
 * @link https://developer.apple.com/notifications/safari-push-notifications/
 * @link http://www.raywenderlich.com/32960/apple-push-notification-services-in-ios-6-tutorial-part-1
 * @link https://github.com/connorlacombe/Safari-Push-Notifications
 * @link http://samuli.hakoniemi.net/how-to-implement-safari-push-notifications-on-your-website/
 * @link https://developer.apple.com/account/ios/identifiers/websitePushId/websitePushIdList.action
 */

namespace safaripush;
use ZIPARCHIVE;

class pushPackageClass {
    //====================== SETUP STUFF ====================
    // Change this to the path where your certificate is located
    public $certificate_path = "/var/www/html/_push/web.com.change_me.notification.p12";
    // Change this to the certificate's import password
    public $certificate_password = change_me;
    //Change this to the $raw_files location
    public $raw_dir = "/var/www/html/change_me";
    //Change this to the website name
    public $website_name = "change_me";

    //====================== DO NOT CHANGE ===================
    //The ID of the user (will be appended to the website.json file)
    protected $userID;
    //Our directory for holding the icons
    private $iconset_dir = 'icon.iconset';
    //The required package icons (these have to be provided)
    private $icons = array(
        'icon_16x16.png',
        'icon_16x16@2x.png',
        'icon_32x32.png',
        'icon_32x32@2x.png',
        'icon_128x128.png',
        'icon_128x128@2x.png'
    );
    
    //Our temp location for holding the file
    private $package_dir;

    /**
     * Initialize
     *
     * @param $userID string The user ID (encrypted)
     */
    public function __construct($userID){
        //Perform basic checks
        if(empty($this->certificate_path)) return error_log('pushPackageClass::__construct() Error: $certificate_path is empty');
        if(empty($this->certificate_password)) return error_log('pushPackageClass::__construct() Error: $certificate_password is empty');
        if(empty($this->raw_dir)) return error_log('pushPackageClass::__construct() Error: $raw_dir is empty');
        if(empty($this->website_name)) return error_log('pushPackageClass::__construct() Error: $website_name is empty');

        $this->userID = filter_var($userID, FILTER_SANITIZE_STRING);

        //Create the temporary folder that will be used by the script
        $package_dir = '/tmp/pushPackage' . time();
        if (!mkdir($package_dir)) {
            unlink($package_dir);
            return error_log('pushPackageClass::__construct() Error: Could not create directory check permissions');
        } else return $this->package_dir = $package_dir;
    }

    /**
     * Returns the push package
     *
     * @return False if ERROR, zip file if OK
     */
    final public function serve_push_package(){
        $package_path = $this->create_push_package();

        if (empty($package_path)) {
            return error_log('pushPackageClass::serve_push_package() Error: Missing $package_path');
        } else {
            header("Pragma: public");
            header("Content-type: application/zip");
            header('Content-Disposition: attachment; filename="pushpackage.zip"');
            header("Content-Length: " . filesize($package_path));

            if(is_readable($package_path)) echo file_get_contents($package_path);
            else return error_log('pushPackageClass::serve_push_package() Error: ' . $package_path . ' is not readable');
        }
    }

    /**
     * Creates the push package, and returns the path to the archive
     *
     * @return string
     */
    private function create_push_package() {
        //Copy files to temp location
        $this->copy_icons();
        //Create website.json file in the temp location
        $this->create_website();
        //Create SHA1 checksum JSON file in the temp location
        $this->create_manifest();
        //Create the signature file in the temp location
        $this->create_signature();
        //Get the path where the complete package was created
        $package_path = $this->package_raw_data();

        return $package_path;
    }

    /**
     * Copies the push package icons to $package_dir.
     */
    private function copy_icons() {
        //Define the location of the images
        $iconset_path = $this->package_dir . DIRECTORY_SEPARATOR . $this->iconset_dir;

        //Create a folder in the temp directory
        if(!mkdir($iconset_path))
            return error_log('pushPackageClass::copy_icons() Error: Could not create directory check permissions');

        //Copy each image to the temp directory
        foreach ($this->icons as $icon) {
            $sourceFile = $this->raw_dir . DIRECTORY_SEPARATOR . $this->iconset_dir . DIRECTORY_SEPARATOR . $icon;
            $targetFile =  $iconset_path . DIRECTORY_SEPARATOR . $icon;

            if(is_readable($sourceFile)){
                if(!copy($sourceFile, $targetFile)) return error_log('pushPackageClass::copy_icons() Error: Could not copy file ' . $sourceFile);
            } else error_log('pushPackageClass::copy_icons() Error: Could not read file ' . $sourceFile);
        }

        return true;
    }

    /**
     * Creates the website.json (with the userID)
     *
     * authenticationToken needs to be at least 16 characters or the package will not work
     */
    private function create_website(){
        $arrWebsite = array(
            "websiteName"=> ucfirst($this->website_name),
            "websitePushID"=> "web.com." . $this->website_name . ".notification",
            "allowedDomains"=> ["https://www." . $this->website_name . ".com"],
            "urlFormatString"=> "https://www." . $this->website_name . ".com?%@",
            "authenticationToken"=> $this->userID,
            "webServiceURL"=> "https://www." . $this->website_name . ".com"
        );

        $websiteFile = fopen($this->package_dir . DIRECTORY_SEPARATOR . "website.json", "w");
        if(!$websiteFile) return error_log("pushPackageClass::create_website() Error: Unable to create file website.json");

        fwrite($websiteFile, json_encode($arrWebsite, JSON_UNESCAPED_SLASHES));
        fclose($websiteFile);

        return true;
    }

    /**
     * Creates the manifest by calculating the SHA1 hashes for all of the files in the package.
     */
    private function create_manifest() {
        $manifest_data = array();

        //Add icons to manifest
        foreach ($this->icons as $raw_icon) {
            $raw_file_directory = $this->package_dir . DIRECTORY_SEPARATOR . $this->iconset_dir . DIRECTORY_SEPARATOR . $raw_icon;
            //Check if all the files in the push package are readable
            if(is_readable($raw_file_directory))
                // Obtain SHA1 hashes of all the files in the push package
                $manifest_data[$this->iconset_dir . DIRECTORY_SEPARATOR . $raw_icon] = sha1(file_get_contents($raw_file_directory));
            else error_log('pushPackageClass::create_manifest() Error: Could not open file ' . $raw_file_directory . 'for reading');
        }

        //Add website.json to manifest
        $websiteJSON = $this->package_dir . DIRECTORY_SEPARATOR . 'website.json';
        if(is_readable($websiteJSON))
            // Obtain SHA1 hashes of all the files in the push package
            $manifest_data['website.json'] = sha1(file_get_contents($websiteJSON));
        else error_log('pushPackageClass::create_manifest() Error: Could not open file ' . $websiteJSON . 'for reading');

        if(!file_put_contents($this->package_dir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode((object)$manifest_data)))
            return error_log('pushPackageClass::create_manifest() Error: Could not create ' . $this->package_dir . DIRECTORY_SEPARATOR . 'manifest.json');

        return true;
    }

    /**
     * Creates a signature of the manifest using the push notification certificate.
     */
    private function create_signature() {
        $certs = array();

        //Define the location of the signature file
        $signature_path = $this->package_dir . DIRECTORY_SEPARATOR . 'signature';

        // Load the push notification certificate
        $pkcs12 = file_get_contents($this->certificate_path);
        if(!$pkcs12) return error_log('pushPackageClass::create_signature() Error: Could not read certificate file');

        //Parse a PKCS#12 Certificate Store into an array
        if(!openssl_pkcs12_read($pkcs12, $certs, $this->certificate_password))
            return error_log('pushPackageClass::create_signature() Error: Could not decrypt certificate file');

        //Parse an X.509 certificate and return a resource identifier for it
        $cert_data = openssl_x509_read($certs['cert']);
        if(!$cert_data) return error_log('pushPackageClass::create_signature() Error: openssl_x509_read() failure');

        //Get the private key
        $private_key = openssl_pkey_get_private($certs['pkey'], $this->certificate_password);
        if(!$private_key) return error_log('pushPackageClass::create_signature() Error: openssl_pkey_get_private() failure');

        // Sign the manifest.json file with the private key from the certificate
        if(!openssl_pkcs7_sign($this->package_dir . DIRECTORY_SEPARATOR . 'manifest.json', $signature_path, $cert_data, $private_key, array(), PKCS7_BINARY | PKCS7_DETACHED))
            return error_log('pushPackageClass::create_signature() Error: Signing failure');

        // Convert the signature from PEM to DER
        $signature_pem = file_get_contents($signature_path);
        if(!$signature_pem) return error_log('pushPackageClass::create_signature() Error: Could not open ' . $this->package_dir . DIRECTORY_SEPARATOR . 'signature');

        $matches = array();
        if (!preg_match('~Content-Disposition:[^\n]+\s*?([A-Za-z0-9+=/\r\n]+)\s*?-----~', $signature_pem, $matches))
            return error_log('pushPackageClass::create_signature() Error: Content does not match');

        $signature_der = base64_decode($matches[1]);
        if(!$signature_der) return error_log('pushPackageClass::create_signature() Error: Could not base64_decode() content');

        if(!file_put_contents($signature_path, $signature_der))
            return error_log('pushPackageClass::create_signature() Error: Could not create signature file');

        return true;

    }

    /**
     * Zips the directory structure into a push package, and returns the path to the archive.
     *
     * @return string
     */
    private function package_raw_data() {
        $zip_path = $this->package_dir . DIRECTORY_SEPARATOR . 'pushpackage.zip';

        // Package files as a zip file
        $zip = new ZipArchive();
        if (!$zip->open($zip_path, ZIPARCHIVE::CREATE)) return error_log('pushPackageClass::package_raw_data() Error: Could not create ' . $zip_path);

        //Create the file list
        $raw_files['website'] = 'website.json';
        $raw_files['manifest'] = 'manifest.json';
        $raw_files['signature'] = 'signature';

        //Add icons to zip
        foreach ($this->icons as $raw_icon) {
            $raw_file_directory = $this->package_dir . DIRECTORY_SEPARATOR . $this->iconset_dir . DIRECTORY_SEPARATOR . $raw_icon;
            if(is_readable($raw_file_directory)) $zip->addFile($raw_file_directory, $this->iconset_dir . DIRECTORY_SEPARATOR . $raw_icon);
            else error_log('pushPackageClass::package_raw_data() Error: Could not read icon ' . $raw_file_directory . ' for adding to zip');
        }

        //Add other files to zip
        foreach ($raw_files as $raw_file) {
            $raw_file_directory = $this->package_dir . DIRECTORY_SEPARATOR . $raw_file;
            if(is_readable($raw_file_directory)) $zip->addFile($raw_file_directory, $raw_file);
            else error_log('pushPackageClass::package_raw_data() Error: Could not read file ' . $raw_file_directory . ' for adding to zip');
        }

        //Compress
        $zip->close();
        return $zip_path;
    }
}
