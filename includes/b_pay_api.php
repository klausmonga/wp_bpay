<?php

// namespace b_pay\api;

class ProcessPayment  {
    private $dev;
    private $bill_to;
    private $p_info;
    private $run_env;
    private $JSONObject_trans;
    private $productsList="";
    private $JSONObject_response;

    public function getDev() {
        return $this->dev;
    }

    public function addDev($dev_key, $business_key) {
        $this->dev = "\"dev\":{\"dev_key\":\"".$dev_key."\",\"business_key\":\"".$business_key."\"}";
    }

    public function getBill_to() {
        return $this->bill_to;
    }

    public function addBill_to($num) {
        $this->bill_to = "\"bill_to\":{\n" .
                "\t  \t\"num\":\"".$num."\"}";
    }

    public function getP_info() {
        return $this->p_info;
    }
    public function addProduct($price, $quantity,$name,$description){
        $this->productsList.=",{\"price\":".$price.",\"quantity\":".$quantity.",\"name\":\"".$name."\",\"description\":\"".$description."\"}";   
    }
    public function getProductsList(){
        return $this->productsList;
    }
    public function addP_info($currency,$tax) {
        $products = $this->getProductsList();
        $products[0] = " ";
        $this->p_info = "\"p_info\":{\"products\":[".$products."],\"currency\":\"".$currency."\",\"tax\":".$tax."}";
    }

    public function getRun_env() {
        return $this->run_env;
    }

    public function addRun_env($return_slip_format) {
        $this->run_env = " \"run_env\":{\n" .
                "\t  \t\"return_slip_format\":\"".$return_slip_format."\"\n" .
                "\t  }";
    }

    public function getTrans(){
        return "{\"trans\":{".$this->getDev().",".$this->getBill_to().",".$this->getP_info().",".$this->getRun_env()."}}";
    }

    public function setTrans($JSONObject_trans) {
        $this->trans = $JSONObject_trans;
    }
    public function commit(){                                                                    
        $data_string = $this->getTrans();                                                                                   
                                                                                                                            
        $ch = curl_init('http://cloudbpay.bvortex.com/index.php/Api/process_payment');                                                                      
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
            'Content-Type: application/json',                                                                                
            'Content-Length: ' . strlen($data_string))                                                                       
        );                                                                                                                   
                                                                                                                            
        $result = curl_exec($ch);
        return $result;
    }
    
}

// $pp = new ProcessPayment();
// $pp->addDev("821267c2bd2f6fb9a98e4ced2eed0063","3d5a3de17a1be6b13e9dd2bb0a45df94");
// $pp->addProduct(500, 1,"techno","telphone techno");
// $pp->addProduct(3, 1,"tshirt","telphone t-shirt");
// $pp->addP_info("cdf","0");
// $pp->addBill_to("0970621196");
// $pp->addRun_env("json");
// $url =$pp->commit();

// // echo gettype($url);
// $josh = explode(':',$url);
// // print_r($josh);

// $tab = ($josh[4]) ;
// echo substr($tab, 0, 4); 


?>