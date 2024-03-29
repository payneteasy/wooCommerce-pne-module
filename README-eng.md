# Content

## [WordPress Installation](#wordpress-installation-1)
## [WooCommerce plugin installation](#woocommerce-plugin-installation-1)
## [Payneteasy Gateway plugin installation and configuration](#payneteasy-gateway-plugin-installation-and-configuration-1)
## [Product creating process](#product-creating-process-1)
## [Payment Flow](#payment-flow-1)
## [List of errors](#list-of-errors-1)

- ***Tested on WooCommerce v 8.0.1***

# WordPress Installation

1. Login to [admin WordPress panel](http://wordpress.org/wp-admin/).
2. Select the language.
   
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/1-eng.jpg" alt="drawing" width="200"/>
   
3. Start WordPress installation.
   
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/2-eng.jpg" alt="drawing" width="450"/>

4. Provide the name of the database and user created earlier.
   
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/3-eng.jpg" alt="drawing" width="450"/>

5. Run the installation.
   
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/4-eng.jpg" alt="drawing" width="450"/>

6. Set the site name, username and password.
  
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/5-eng.jpg" alt="drawing" width="450"/>

7. Finish the installation process.
    
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/6-eng.jpg" alt="drawing" width="450"/>
   
# WooCommerce plugin installation

1. Go to the [**Plugins**](http://wordpress.org/wp-admin/plugins.php) tab and click the `Add New` button.
2. Find the *wooCommerce* plugin in the search field.
3. Install it by clicking `Install Now` button.

   ![](https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/7-eng.jpg)
   
4. Activate the plugin. Click on the `Activate` button.

   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/8-eng.jpg" alt="drawing" width="350"/>
   
5. Select the Industry field, type of products and region and click `Continue` button.

   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/10-eng.jpg" alt="drawing" width="450"/>

# Payneteasy Gateway plugin installation and configuration

1. In the **/wp-content/plugins/** folder create directory **paynet-easy-gateway**.

```bash
cd /wp-content/plugins/
mkdir paynet-easy-gateway
```

2. Recursively copy the **wooCommerce-pne-module** directory content (not the directory itself) to the directory **/wp-content/plugins/paynet-easy-gateway**.

```bash
cp -r wooCommerce-pne-module/* /wp-content/plugins/paynet-easy-gateway
```

Content of the directory *wooCommerce-pne-module*

* admin
* docs
* images
* index.php
* languages
* LICENSE.txt
* PaynetEasy
* paynet-easy-gateway.php
* public
* README.md
* templates
* uninstall.php


3. Go to the **Plugins** tab and activate *Payneteasy Gateway* plugin by clicking `Activate` button.
   
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/11-eng.jpg" alt="drawing" width="500"/>
   
4. Go to the *WooCommerce* plugin `1` payment customization page [**Woocommerce/Settings/Payments**](http://wordpress.org/wp-admin.php?page=wc-settings&tab=checkout) `2`, `3`. Enable *Payneteasy Method* `4` and drag it to the top `5`. Save changes.

   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/12-eng.jpg" alt="drawing" width="1000"/>
   
5. Go to the [*Payneteasy Gateway* plugin customization page](http://wordpress.org/wp-admin/admin.php?page=wc-settings&tab=checkout&section=payneteasy) by clicking on it.

   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/13-eng.jpg" alt="drawing" width="500"/>

| Parameter         | Description                                                                                                                                                        | 
|-------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Enable/Disable    | Enable or disable PaynetEasy Gateway plugin                                                                                                                        |
| Title             | This field controls the title which the user sees during checkout                                                                                                  |
| Description       | This field controls the description which the user sees during checkout                                                                                            |
| Sandbox test mode | Place the payment gateway in development mode                                                                                                                      |
| Log level         | One of the log levels must be chosen: `EMERGENCY`, `ALERT`, `CRITICAL`, `ERROR`, `WARNING`, `NOTICE`, `INFO`, `DEBUG`                                              |
| Integration mode  | Acceptance of payment details is implemented on the PaynetEasy gateway side. One of the integration methods must be chosen: `Inline form`, `Remote`                |
| End point         | The End point ID is an entry point for incoming Connecting Party’s transactions for single currency integration. Either End point or End point group must be used  |
| End point group   | The End point group ID is an entry point for incoming Merchant’s transactions for multi-currency integration. Either End point or End point group must be used     |
| Login             | Connecting Party’s login name                                                                                                                                      |
| Control key       | Connecting Party’s control string for sign                                                                                                                         |
| Gateway url       | Payment Gateway's URL                                                                                                                                              |

**Note** For sandbox mode all sandbox parameters must be used.

# Product creating process

Go to the **Products** tab on the Sidebar and create a new product.

   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/14-eng.jpg" alt="drawing" width="1000"/>

Product parameters that should be filled:

* Name `1`
* Description `2`
* Price `3`
* After all fields are filled, press the `Publish` `4` button to publish the product.

  <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/15-eng.jpg" alt="drawing" width="1000"/>

# Payment Flow

1. Enter to the `Shop` and start the Payment Flow.
   
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/16-eng.jpg" alt="drawing" width="600"/>
   
2. Add a product to the card by clicking on the `Add to card` button and then view the card.

   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/17-eng.jpg" alt="drawing" width="200"/>
   
3. Select the quantity of the product and click on `Proceed to checkout` button to start the payment process.
   
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/18-eng.jpg" alt="drawing" width="600"/>
   
4. Fill the payment form and press the `Place Order` button.
   
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/9-eng.jpg" alt="drawing" width="600"/>

| Parameter         | Description                                                                                                                                        | 
|-------------------|----------------------------------------------------------------------------------------------------------------------------------------------------|
| First name        | Payer's first name                                                                                                                                 |
| Last name         | Payer's last name                                                                                                                                  |
| Country/Regional  | Payer's country                                                                                                                                    |
| Street address    | Payer's address line                                                                                                                               |
| Town/City         | Payer's city                                                                                                                                       |
| State/Country     | Payer's state                                                                                                                                      |
| Postcode/ZIP      | Payer's ZIP code                                                                                                                                   |
| Phone             | Payer's full international number, including country code                                                                                          |
| E-mail address    | Payer's e-mail address                                                                                                                             |
| Card number       | Payer's credit card number                                                                                                                         |
| Card printed name | Cardholder name, printed on the bank card                                                                                                          |
| Expiry month      | Bank card expiration month                                                                                                                         |
| Expiry year       | Bank card expiration year                                                                                                                          |
| Card Code (CVC)   | Payer's CVV2 code. CVV2 (Card Verification Value) is a three- or four-digit number AFTER the credit card number in the signature  area of the card |


5. Start the redirecting process.


   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/20-eng.jpg" alt="drawing" width="550"/>


6. The payer is redirecting to the wait form.

   
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/23.jpg" alt="drawing" width="550"/>

   
7. The payer is redirecting to the finish form.

   
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-eng/21-eng.jpg" alt="drawing" width="550"/>


# List of errors

1. **Error:** `Callback URL cannot be local or private`

**Solution**

In the **/var/www/html/wp-content/plugins/paynet-easy-gateway/PaynetEasy/WoocommerceGateway/WCIntegration.php** file replace the local IP `home_url('/')` with external URL or IP, for example, `https://httpstat.us/200`.

2. **Error:** `Project with X currency doest not apply request with currency Y`

**Solution**

Incorrect currency. WooCommerce currency should match to the Payment Gateway's endpoint currency. It can be changed in the [WooCommerce general currency settings](http://wordpress.org/wp-admin/admin.php?page=wc-settings)

3. **Error:** `Amount is less/higher than minimum/maximum X`

**Solution**

Endpoint limits must be checked.

4. **Error:** `Internal server error`

**Solution**

WooCommerce system files must be checked. Most likely, the problem in the .php files - incorrect manual configurations, damaged files etc. Good solution is updating or reinstalling the repository.

5. **Error:** `Error occured. HTTP code: '50x'` 

**Solution**

Gateway URL parameter in *Payneteasy Gateway* plugin configurations must be corrected. Example: *https://sandbox.payneteasy.eu/paynet/api/v2*.

6. **Error:** `Property 'signingKey' does not defined in PaymentTransaction property 'queryConfig'`

**Solution**

Incorrect merchant Control Key. [Payneteasy Gateway settings](http://wordpress.org/wp-admin/admin.php?page=wc-settings&tab=checkout&section=payneteasy) must me checked.

7. **Error:** `Some Request fields are invalid: Gateway url does not valid in Request`

**Solution**

Incorrect Gateway url. [Payneteasy Gateway settings](http://wordpress.org/wp-admin/admin.php?page=wc-settings&tab=checkout&section=payneteasy) must me checked.

8. **Error:** `End point with id 0 not found`

**Solution**

Incorrect Endpoint or Login field is empty/incorrect. [Payneteasy Gateway settings](http://wordpress.org/wp-admin/admin.php?page=wc-settings&tab=checkout&section=payneteasy) must me checked.
