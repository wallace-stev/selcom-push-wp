# selcom-push-wp
A plugin providing a custom solution to enable mobile payments integration via Selcom USSD Push for WordPress websites running on WooCommerce.


## Motivation
This plugin comes to provide a comprehensive solution to mobile payments integration for local e-commerce service providers as the demand increases. We found that several developers struggle when it comes to these necessary yet unmet provisions serving as tools to simplify such works.


## Prerequisites
Before one choses this tool for their particular project(s), it is necessary for them to have developer-level understanding of PHP (Preferrably 7.4 and above), WordPress, WooCommerce, and using API(s) as it may come in handy when facing problems. Also, they should contact Selcom and request for merchant account for them to get necessary information such as security tokens and others required to make this plugin work successfully.


## Code style
A guide for those wanting to contribute to the project and make improvements to the code.

[![indentation](https://img.shields.io/badge/indentation-tabs-brightgreen)](https://www.codementor.io/@aviaryan/tabs-v-s-spaces-an-analysis-on-why-tabs-are-better-96xr0bg32)
[![coding-style](https://img.shields.io/badge/style-object--oriented-brightgreen)](https://en.wikipedia.org/wiki/Object-oriented_programming)


## Languages used
- [PHP](https://www.php.net)


## Features
- Send direct USSD Push notification to client's mobile
- Simplified checkout procedure, with order status updates as payments occur


## Code
This plugin makes use of [cURL](https://www.php.net/manual/en/book.curl.php) to send HTTP POST requests.<br/>
Sample code:
```php
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    if($isPost){
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch,CURLOPT_TIMEOUT,90);
    $result = curl_exec($ch);
    curl_close($ch);
```
Alternatively, one might prefer using the [WordPress HTTP API](https://developer.wordpress.org/plugins/http-api/) to do so (click the link to see documentation).

## Installation & Usage
Just download the files and install to your website as you would for any WordPress plugin. Activate the plugin then go to WooCommerce payment settings and provide the *API Key, API Secret, Merchant ID & API URL* values with the ones provided by Selcom upon merchant account provision. Finally, activate the payment option, test it and if no errors occur, change to the production URL and you're set to receive mobile payments to your merchant wallet.

## API Reference
- [Selcom API Reference](https://developers.selcommobile.com/#introduction)
- [WooCommerce API Reference](https://woocommerce.github.io/woocommerce-rest-api-docs/#introduction)
- [WordPress Developer Resources](https://developer.wordpress.org)

## License
[![GNU-GPL-3](https://img.shields.io/github/license/wallace-stev/epush-selcom-wp)](https://www.gnu.org/licenses/gpl-3.0.en.html)

## Sample Websites
- [Agiza Master](https://agizamaster.com)


## Disclaimer & Copyrights
Selcom and the Selcom Logo are registered trademarks of [Selcom Paytech LTD](https://www.selcom.net).
