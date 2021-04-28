<?php

namespace App;

require 'vendor/autoload.php';

class Scrape
{
    private array $products = [];

    private $num_pages;
    private $products_name  = [];
    private $products_price = [];
    private $products_capacity = [];
    private $products_image = [];
    private $products_color = array([]);
    private $products_availability_text = [];
    private $isAvailable = [];
    private $products_shipping_text = [];  
    private $products_shipping_date = [];  

    public function setPagination($document) {
        $pag_value = $document->filter('#products > h2 + p')->text();
        $max_pag = substr($pag_value, -1);
        $this->num_pages = (int)$max_pag;
    }


    public function getNumItemsPerPage($url) {
       $scraper = ScrapeHelper::fetchDocument($url);
       $title_h3 =  $scraper->filter("h3");
       return $title_h3->count();
    }


    public function getAvailabilityText($document) {

        unset($this->products_availability_text);
        $AvailabilityText = $document->filter('.my-8')->filter(".block")->filter(".text-center")->filter(".text-lg + div");

        foreach($AvailabilityText as $key => $availability) {
            $availability_text = trim($availability->nodeValue);
            if(strstr($availability_text, "In Stock")) {
                $this->isAvailable[] = true;
            }
            else {
                $this->isAvailable[] = false;
            }

            $this->products_availability_text[] = str_replace("Availability: ", "", $availability_text);
        }
    }


    public function getShippingText($url){

        unset($this->products_shipping_text);
        $scraper = ScrapeHelper::fetchDocument($url);

        $card_per_pag = $this->getNumItemsPerPage($url);

        for($i=1; $i<=$card_per_pag; $i++){
                $nodes = $scraper->filterXPath('//*[@id="products"]/div[1]/div[' . $i . ']/div/div[3]')->nextAll();        
                
                if($nodes->count() == 0) {
                    $this->products_shipping_text[] = "";
                    $this->getShippingDate("");
                }
                else
                foreach($nodes as $key => $node) {

                    $shipping_text = preg_replace('/\s+/S', " ", trim($node->nodeValue));
                    $this->products_shipping_text[] = $shipping_text; 

                    $this->getShippingDate($shipping_text);
                }
                $this->products_shipping_text = array_values($this->products_shipping_text);
        }
    }


    public function getPagination(){
        return $this->num_pages;
    }

    public function getProductName($document){
        
        unset($this->products_name);
        $products = $document->filter('.product-name');

            foreach($products as $key => $domElement){
                $this->products_name[$key] = $domElement->nodeValue; 
            }
    }


    public function getPrice($document){

        unset($this->products_price);
        $prices = $document->filter('.my-8')->filter(".block")->filter(".text-center")->filter(".text-lg");

        foreach($prices as $key => $domElement) {         
                $price = preg_replace("/[\s]/", "", $domElement->nodeValue); 
                $this->products_price[$key] = (float)substr($price,2, strlen($price)); 
        }
    }


    public function getCapacity($document){

        unset($this->products_capacity);
        $capacities = $document->filter('.product-capacity');

        foreach($capacities as $key => $domElement){

                $size = substr($domElement->nodeValue, 0);
                preg_replace('/^\d+\s+/', '', $size);
    
                    if(strstr($size, "GB")) {

                    $new_capacity = substr($size, 0, strlen($size)-2);
                    $castToMB = (int)$new_capacity * 1024;  
                    $this->products_capacity[] = $castToMB;  
                 }
                 else {
                    $this->products_capacity[] = (int)substr($size, 0, strlen($size)-2);
                 }
        }

    }


    public function getImage($document){

        unset($this->products_image);
        $images = $document->filterXpath('//img')->extract(array('src'));

        foreach($images as $key => $DomElement){
            $this->products_image[] = "https://www.magpiehq.com/developer-challenge" . substr($DomElement, 2, strlen($DomElement)); 
        }

    }


    public function getColors($document){

        $colors = $document->filterXpath('//span')->extract(array('data-colour'));
        $article = 0;

        foreach($colors as $key => $domElement){
            
            if($domElement != "") {
                $this->products_color[$article][] = $domElement;
            }
            else {
                $article++;
            }            
            
            foreach($this->products_color as $key => $color ) {
                if($this->products_color[$key] == null){
                    array_splice($this->products_color, $key, 1);
                }
            }
        }

        $arr = array([]);
        $i=0;
        
        foreach($this->products_color as $key => $val){
            $arr[$i][] = $this->products_color[$key];
        $i++;
        }
        
        unset($this->products_color);
        $this->products_color = $arr;
    }


    public function getShippingDate($strShipping){

        $ret = preg_match("/(\d+\s\w+\s\d+|\d+[th].+?|\d+-\d+-\d+)$/", $strShipping, $retArray);
        
        if(count($retArray) > 0){
            $t_stamp =  strtotime($retArray[1]) . "\n";
            $newDate = date("Y-m-d", (int)$t_stamp);
            $this->products_shipping_date[] =  $newDate;
        }
        else
        $this->products_shipping_date[] =  "";
    }


    public function makeProductsArray($url){

        $card_per_pag = $this->getNumItemsPerPage($url);

        // echo "\$card_per_pag $card_per_pag \n";
                for($i=0; $i<$card_per_pag; $i++){      
                    $this->products[] = 
                                [
                                    "title" => $this->products_name[$i],
                                    "price" => $this->products_price[$i],       
                                    "imageUrl" => $this->products_image[$i],    
                                    "capacityMB" => $this->products_capacity[$i],
                                    "colour" => (object)array_pop($this->products_color[$i]),   
                                    "availabilityText" => $this->products_availability_text[$i],
                                    "isAvailable" => $this->isAvailable[$i],
                                    "shippingText" => $this->products_shipping_text[$i], 
                                    "shippingDate" => $this->products_shipping_date[$i]                                 
                                ];        
                                unset($this->products_color[$i]);
                            }
                            unset($this->isAvailable);
                            unset($this->products_shipping_date);
    }


    public function init_all_array($pagination){

        for($i=1; $i<=$pagination; $i++) {

            $url = "https://www.magpiehq.com/developer-challenge/smartphones/?page=$i";
                $document = ScrapeHelper::fetchDocument($url);
                
                $this->getProductName($document);
                $this->getCapacity($document);
                $this->getPrice($document);
                $this->getImage($document);
                $this->getColors($document);                        
                $this->getAvailabilityText($document);
                $this->getShippingText($url);
                    
                $this->makeProductsArray($url);
            
        }
            

    }
    
    public function run(): void
    {
        $url = "https://www.magpiehq.com/developer-challenge/smartphones";
        $document = ScrapeHelper::fetchDocument($url);

        $this->setPagination($document);

        $this->init_all_array($this->getPagination());
                
        file_put_contents('output.json', json_encode($this->products, JSON_PRETTY_PRINT));

        echo "scraping has finished, please, check your output.json for the result\n";
    }
}

$scrape = new Scrape();
$scrape->run();
