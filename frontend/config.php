<?php 
// CONFIG - These control the look and details on your site. Consult documentation for more details.

// GENERAL

// Page title for your site
define('title', 'UniLinks - URL Shortener'); 

// The short title of your site, used in the footer and in some sub pages
define('shortTitle', 'UniLinks');

// A description of your site, shown on the homepage.
define('description', 'Create short, memorable links for easy sharing. Fast, reliable, and secure URL shortening service.'); 

// The favicon for your site
define('favicon', '/frontend/assets/img/favicon.ico');

// Logo for your site, displayed on home page
define('logo', '/frontend/assets/img/logo-black.png');

// Enable reCAPTCHA V3
// It is highly recommended you use reCAPTCHA V3. It will stop spam. You can get a site and secret key from here: https://www.google.com/recaptcha/admin/create
define("enableRecaptcha", false);

// reCAPTCHA V3 Site Key
define("recaptchaV3SiteKey", 'YOUR_SITE_KEY_HERE');

// reCAPTCHA V3 Secret Key
define("recaptchaV3SecretKey", 'YOUR_SECRET_KEY_HERE');

// Enable authentication requirement for shortening links
// true = only authenticated users can shorten, false = anyone can shorten
// default: false
define('requireAuth', false);

// Enables the custom URL field
// true or false
define('enableCustomURL', true);

// Optional
// Set a primary colour to be used. Default: #007bff
// Here are some other colours you could try:
// #f44336: red, #9c27b0: purple, #00bcd4: teal, #ff5722: orange
// UniLinks uses Unibas Mint: #A5D7D2
define('colour', '#A5D7D2');

// Optional
// Set a background image to be used.
// default: picsum.photos random photos
// define('backgroundImage', 'https://picsum.photos/1920/1080');

// FOOTER

// These are the links in the footer. Add a new link for each new link.
// The array follows a title link structure:
// "TITLE" => "LINK",
$footerLinks = [
    "About"   =>  "https://www.unibas.ch/",
    "Contact" =>  "https://www.unibas.ch/",
    "Admin"   =>  "/admin"
];

?>
