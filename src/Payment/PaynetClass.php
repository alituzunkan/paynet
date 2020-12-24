<?php


/**
 *
 * Bu yardımcı sınıf ile Paynet API'yi PHP projelerinizde kolayca kullanabilirsiniz
 *
 * */


class PaynetClient

{ 	private $isLive;
	private $ApiKey;
	private $PaynetUrl;
	private $json_result;
	private	$paynetSupportedTlsVersions = array("tlsv1.1", "tlsv1.2");

	//adreslerin sonu slaş ile bitmeli
	const testUrl = 'https://pts-api.paynet.com.tr/';
	const liveUrl = 'https://api.paynet.com.tr/';


	/**
	 * Yapıcı metod, secret keyi girmek için kullanılıyor
	 * @param string $apikey
	 * @param bool $isLive Canlı için true, test için false girilmeli
	 */
	public function __construct($ApiKey, $isLive = false)
	{
		$this->ApiKey = $ApiKey;
		$this->isLive = $isLive;
		$this->PaynetUrl = $isLive == false ? self::testUrl : self::liveUrl;
		return $this;

	}




	/**
	 * Paynet apisinden sorgu yapıp cevabı JSON olarak alan metod
	 * @param string $adres_eki
	 * @param stdClass $data
	 * @return mixed sunucudan alınan JSON
	 */
    private function LoadJson($adres_eki, $data)
    {
		if(self::IsFileGetContentsActive())
		{
			$params = array_filter((array) $data);
			$options = array(
							'http' => array(
							'header'  =>"Accept: application/json; charset=UTF-8\r\n".
										"Content-type: application/json; charset=UTF-8\r\n".
										"Authorization: Basic ".$this->ApiKey,
							'method'  => 'POST',
							'content' => json_encode($params),
							'ignore_errors' => true,
							'ssl'=>array(
								"verify_peer"=>false,
								"verify_peer_name"=>false
							)
						)
			);

			if (!function_exists('stream_context_create'))
			{
				die("Sunucunuz stream_context_create() fonksiyonunu desteklememektedir...");
			}

			$context  = stream_context_create($options);
			$sonuc = json_decode(file_get_contents($this->PaynetUrl.$adres_eki, false, $context));

			if($sonuc == null)
			{
				Throw new PaynetException($this->PaynetUrl." adresine bağlanılamadı...");
			}
			else
			{
				return $sonuc;
			}
		}

		else if (self::IsCurlActive())
		{
			$data_string = json_encode($data);
			$address = $this->PaynetUrl.$adres_eki;
			$ch = curl_init($address);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSLVERSION, 6);
		    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //****
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Authorization: Basic '.$this->ApiKey,
				'Content-Type: application/json',
				'Accept: application/json; charset=UTF-8',
				'Content-Length: ' . strlen($data_string))
			);
			$result = curl_exec($ch);

			if($result == false)
			{
				Throw new PaynetException($this->PaynetUrl." adresine şu sebeple bağlanılamadı: ".curl_error($ch));
			}

			else
			{
				return json_decode($result);
			}
		}

		else
		{
			Throw new PaynetException("Sunucunuzda cURL veya file_get_contents() desteklememektedir.");
		}
	}



	public function CheckTls()
	{
		$result = new Result();
		$serverTlsVersions = stream_get_transports();

		foreach($this->paynetSupportedTlsVersions  as $paynetSupportedTlsVersion)
		{
			if (in_array($paynetSupportedTlsVersion, $serverTlsVersions))
			{
				$result->message = "TLS kontrolü başarılı.";
				$result->code = ResultCode::successful;
				return $result;
			}
		}

		$paynetSupportedTlsVersions = join(', ', $this->paynetSupportedTlsVersions);
		$serverTlsVersions = join(', ', $serverTlsVersions);
		//burada paynetin desteklediği, ve sunucuda mevcut bulunanlar listelenmeli
		$result->message = "Sunucunuz gerekli kriptolama protokolü versiyonlarını desteklememektedir.(Paynet’in desteklediği versiyonlar:".$paynetSupportedTlsVersions.". Sunucunuzun desteklediği versiyonlar: ".$serverTlsVersions.").";
		$result->code = ResultCode::unsuccessful;
		return $result;
	}

	public function IsFileGetContentsActive()
	{
		if( ini_get('allow_url_fopen'))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function IsCurlActive()
	{
		if(in_array ('curl', get_loaded_extensions()))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function CheckConnectionClients()
    {
		$result = new Result();

		if( $this->IsFileGetContentsActive() || $this->IsCurlActive())
		{

			$result->message = "Bağlantı kontrolü başarılı.";
			$result->code = ResultCode::successful;
			return $result;
		}
		else
		{
			$result->message = "Sunucunuz cURL veya file_get_contents() desteklememektedir.";
			$result->code = ResultCode::unsuccessful;
			return $result;
		}
	}



	/**	TLS kontrolü yapılır
	  *	Uygun bir bağlantı client'i olup olmadığı kontrol edilir
	  *	Paynet entegrasyon bilgilerinin doğru olduğu kontrol edilir
	*/

	public function CheckConnectionAndIntegrationInfo($parameters)
	{
		//TLS
		$checkTlsResult = $this->CheckTls();

		if($checkTlsResult->code != ResultCode::successful)
	    {
			return $checkTlsResult;
		}

		$checkConnectionClientsResult = $this->CheckConnectionClients();

	    if($checkConnectionClientsResult->code != ResultCode::successful)
		{
			return $checkConnectionClientsResult;
		}

		$checkIntegrationPostResult = $this->CheckIntegrationPost($parameters);

		return $checkIntegrationPostResult;
    }

    public function CheckIntegrationPost(CheckIntegrationParameters $parameters)
	{
		$this->json_result = $this->LoadJson('v1/agent/check_integration_info',$parameters);
		$sonuc = new CheckIntegrationResponse();
		$sonuc->fillFromJson($this->json_result);
		return $sonuc;
	}

	/**
	 * Karttan çekim işlemini yapan metod
	 * @param ChargeParameters $param
	 * @return ChargeResponse
	 */
	public function ChargePost(ChargeParameters $param)
	{
		$this->json_result = $this->LoadJson('v1/transaction/charge',$param);
        $sonuc = new ChargeResponse();
		$sonuc->fillFromJson($this->json_result);
		return $sonuc;
	}

	/**
	 *
	 * @param CheckTransactionParameters $param
	 * @return CheckTransactionResponse
	 */
	public function CheckTransaction(CheckTransactionParameters $param)
	{
		$this->json_result = $this->LoadJson("v1/transaction/check", $param);
		$this->json_result = $this->json_result->Data[0];
		$sonuc = new CheckTransactionResponse();
		$sonuc->fillFromJson($this->json_result);
		return $sonuc;
	}

	/**
	 *
	 * @param TransactionDetailParameters $param
	 * @return TransactionDetailResponse
	 */
	public function GetTransactionDetail(TransactionDetailParameters $param)
	{
		$this->json_result = $this->LoadJson('v1/transaction/detail',$param);
		$sonuc = new TransactionDetailResponse();
		$sonuc->FillFromJson($this->json_result);
		return $sonuc;
	}

	public function ListTransaction(TransactionListParameters $param)
	{
		$this->json_result = $this->LoadJson('v1/transaction/list', $param);
		$sonuc = new TransactionListResponse();
		$sonuc->fillFromJson($this->json_result);
		return $sonuc;
    }

	/**
	 * Mail veya sms ile ödeme seçemekleri için link üreten servis...
	 * @param MailOrderParameters $params
	 * @return MailOrderResult
	 */
	public function CreateMailOrder(MailOrderParameters $params)
	{
		$this->json_result = $this->LoadJson('v1/mailorder/create', $params);
		$sonuc = new MailOrderResult();
		$sonuc->fillFromJson($this->json_result);
		return $sonuc;
	}

	/**
	 * Oran tablosunu getiren servis
	 * @param RatioParameters $params
	 * @return RatioResponse
	 */
	public function GetRatios(RatioParameters $params)
	{
		$this->json_result = $this->LoadJson("v1/ratio/Get", $params);
		$sonuc = new RatioResponse();
		$sonuc->fillFromJson($this->json_result);
		return $sonuc;
	}

	/**
	 * İşlemi işaretleyen servis
	 * @param MarkTransferParameters $params
	 * @return boolean
	 */
	public function MarkTransferred(MarkTransferParameters $params)
	{
		$this->json_result = $this->LoadJson("v1/transaction/mark_transferred", $params);
		return $this->json_result->code == "1";
    }

	/**
	 *
	 * @param ReversedRequestParameters $params
	 * @return ReversedRequestResponse
	 */
	public function ReversedRequest(ReversedRequestParameters $params)
	{
		$this->json_result = $this->LoadJson("v1/transaction/reversed_request", $params);
		$sonuc = new ReversedRequestResponse();
		$sonuc->fillFromJson($this->json_result);
		return $sonuc;
	}

	/**
	 *
	 * @param AutologinParameters $params
	 * @return AutologinResult
	 */
	public function AutoLogin(AutologinParameters $params)
	{
		$this->json_result = $this->LoadJson("v1/agent/autologin", $params);
		$sonuc = new AutologinResult();
		$sonuc->fillFromJson($this->json_result);
		return $sonuc;
	}

	/**
	 * Bir işlem sonucunda sunucudan alınan JSON nesnesini table olarak ekrana yazdırır
	 */
	public function PrintResult()
	{
		if($this->json_result!=null)
		{
			echo "<hr>SUNUCU YANITI<br><table>";
			foreach($this->json_result as $property => $value)
			{
				echo "<tr>\r\n";
					echo "<td>".$property."</td>\r\n";
					echo "<td>".$value."</td>\r\n";
				echo "</tr>\r\n";
			}
			echo "<table>";
		}
		else
			echo "Sonuç değişkeni boş";
	}

	/**
	 *Bir servisin cevap olarak gönderdiği Json nesnesini düz yazı olarak ekrana yazar.
	 */
	public function PrintJson()
	{
		echo json_encode($this->json_result);
	}






}





/**
 * Suncudan dönen cevaplar için base sınıfı
 * @author proje
 */
class Result extends fillFromJson_
{
	public $object_name;
	public $code;
	public $message;
}


class  ResultCode
{
	      const	successful                   = 0;
          const unsuccessful                 = 1;
     	  const company_blocked              = 2;
      	  const agent_blocked                = 3;
    	  const agent_not_found              = 4;
      	  const duplicate_data               = 5;
     	  const no_process                   = 6;
      	  const unauthorized                 = 7;
     	  const server_error                 = 8;
      	  const not_implemented              = 9;
    	  const time_out                     = 10;
    	  const bad_request                  = 11;
    	  const no_data                      = 12;
    	  const paynetj_no_session           = 13;
      	  const paynetj_wrong_bin            = 14;
      	  const paynetj_unmatch_tran         = 15;
		  const paynetj_3d_error             = 16;
     	  const paynetj_used_session         = 17;
      	  const wrong_card_data              = 18;
      	  const wrong_transaction_type       = 19;
      	  const wrong_pos_type               = 20;
      	  const wrong_ratio_get              = 21;
     	  const paynetj_expire_date_error    = 22;
     	  const ratio_code_not_found         = 23;
     	  const invoice_no_not_found         = 24;
     	  const card_not_found               = 25;
     	  const card_key_undefined           = 26;
     	  const old_successful               = 100;
     	  const subscription_on              = 200;
     	  const subscription_off             = 201;


}







/**
 * FillFromJson metodunu diğer sınıflara eklemek için
 * @author proje
 *
 */
class fillFromJson_
{
	/**
	 * Json olarak alınan bilgileri oluşturulmuş nesneye yükler.
	 * @param jsonObject $json
	 */
	function fillFromJson($json)
	{
		foreach($this as $property=>$value)
		{
			if(isset($json->$property))
			{
				//Eğer property bir dizi ise ve ilk elemanı sınıf ismi olarak tanımlanmışsa
				if(is_array($this->$property))
				{
					$array = $this->$property;
					if(count($array) && is_string($array[0]))
					{
						//Çocuk sınıfın adını döndür ve diziyi temizle
						$child_class_name = array_pop($this->$property);

						//Json'daki herbir dizi elemenı için ayrı nesne oluşturulacak
						foreach($json->$property as $data)
						{
							//Çocuk nesneyi oluştur ve içeriğini doldur
							$child_obj = new $child_class_name;
							$child_obj->fillFromJson($data);
							array_push($this->$property, $child_obj);
						}
					}
				}
				//dizi değilse değer ataması yapmak yeterli
				else
				{
					$this->$property = $json->$property;
				}
			}
		}
	}
}





/**
 * CheckIntegration servisi için request parametreleri
 * @author ahmet
 *
 */

class CheckIntegrationParameters
{
	public $agent_id;
	public $publishable_key;
	public $secret_key;

}







/**
 * Charge servisi için request parametreleri
 * @author proje
 *
 */
class ChargeParameters
{
	public $session_id;
	public $token_id;
	public $reference_no = "";
	public $transaction_type = 1;
	public $amount;
}




/**
 * ChargePost metodundan dönecek sonuç nesne için
 * @author proje
 *
 */
class ChargeResponse extends Result
{
	public $xact_id;
	public $xact_date;
	public $transaction_type;
	public $pos_type;
	public $is_tds;
	public $agent_id;
	public $user_id;
	public $email;
	public $phone;
	public $bank_id;
	public $instalment;
	public $card_no_masked;
	public $card_holder;
	public $amount;
	public $net_amount;
	public $comission;
	public $comission_tax;
	public $currency;
	public $authorization_code;
	public $reference_code;
	public $order_id;
	public $is_succeed;
	public $paynet_error_id;
	public $paynet_error_message;
	public $bank_error_id;
	public $bank_error_message;
	public $bank_error_short_desc;
	public $bank_error_long_desc;
	public $agent_reference_no;
	public $ratio;
	public $ratio_code;
	public $end_user_comission;

}

class CheckIntegrationResponse extends Result
{
	public $object_name;
	public $code;
	public $message;

}



/**
 * GetRatios() metodunun parametreleri
 * @author proje
 *
 */
class RatioParameters
{
	public $pos_type = 5;
	public $bin;
	public $amount;
	public $addcommission_to_amount = false;
    public $ratio_code;
}




/**
 * GetRatios() metodunun dönüş sınıfı
 * @author proje
 *
 */
//Ratio servisinin cevabı
class RatioResponse extends Result
{
    public $data = array('Banks');//banka  listesi
}



/**
 * Banks sınıfının ratio dizinin elemanları
 * @author proje
 *
 */

class Ratios extends fillFromJson_
{
    public $ratio;
    public $instalment_key;
    public $instalment;
    public $instalment_amount;
    public $total_net_amount;
    public $total_amount;
    public $commision;
    public $commision_tax;
    public $desc;
	public $ratio_code;

}



/**
 * RatioResponse sınıfındaki data dizisinin elemanları
 * @author proje
 *
 */
class Banks extends fillFromJson_
{
    public $bank_id;
    public $bank_logo;
    public $bank_name;
    public $ratio = array('Ratios');
}





/**
 * CheckTransaction metoduna gönderilecek paramatre
 * @author proje
 *
 */
class CheckTransactionParameters
{
	public $xact_id;
	public $reference_no;
}




/**
 * CheckTransaction metodunun sonucunda dönecek sınıf
 * @author proje
 *
 */
class CheckTransactionResponse extends Result
{
	public $xact_id;
	public $xact_date;
	public $transaction_type;
	public $pos_type;
	public $agent_id;
	public $is_tds;
	public $bank_id;
	public $instalment;
	public $card_no;
	public $card_holder;
	public $card_type;
	public $ratio;
	public $ratio_code;
	public $amount;
	public $netAmount;
	public $comission;
	public $comission_tax;

	public $end_user_comission;
	public $currency;
	public $authorization_code;
	public $reference_code;
	public $order_id;
	public $is_succeed;
	public $xact_transaction_id;
	public $email;
	public $phone;
	public $note;
	public $agent_reference;
}




/**
 * GetTransactionDetail metodunun parametreleri
 * @author proje
 *
 */
class TransactionDetailParameters
{
	public $xact_id;
	public $reference_no;
}




/**
 * GetTransactionDetail metodunun dönüşü
 * @author proje
 *
 */
class TransactionDetailResponse extends Result
{
	public $Data = array('TransactionDetail');
}




/**
 * GetTransactionDetail metodunun dönüşündeki satırlar
 * @author proje
 *
 */
class TransactionDetail extends Result
{
	public $xact_id;
	public $xact_date;
	public $transaction_type;
	public $pos_type;
	public $agent_id;
	public $is_tds;
	public $bank_id;
	public $instalment;
	public $card_no;
	public $card_holder;
	public $card_type;
	public $ratio;
	public $ratio_code;

	public $end_user_comission;
	public $amount;
	public $netAmount;
	public $comission;
	public $comission_tax;
	public $currency;
	public $authorization_code;
	public $reference_code;
	public $order_id;
	public $is_succeed;
	public $reversed;
	public $reversed_xact_id;
	public $xact_transaction_id;
	public $email;
	public $phone;
	public $note;
	public $agent_reference;
	public $company_amount;
	public $company_commission;
	public $company_commission_with_tax;
	public $company_net_amount;
	public $agent_amount;
	public $agent_commission;
	public $agent_commission_with_tax;
	public $agent_net_amount;
	public $company_cost_ratio;
	public $company_pay_ratio;
	public $xact_type_desc;
	public $bank_name;
	public $payment_string;
	public $pos_type_desc;
	public $agent_name;
	public $company_name;
	public $instalment_text;
	public $ipaddress;
	public $client_id;

}





/**
 * ListTransaction metodunun parametreleri
 * @author proje
 *
 */
class TransactionListParameters
{
	public $agent_id;
	public $bank_id;
	public $datab;
	public $datbi;
	public $show_unsucceed;
	public $limit;
	public $ending_before;
	public $starting_after;

	public function __construct()
	{
		$this->agent_id = "";
		$this->bank_id = "";
		$this->show_unsucceed = true;
		$this->limit = 1000;
		$this->ending_before = 0;
		$this->starting_after = 0;
		$this->datab = date('Y-m-d', strtotime('-10 days', strtotime(date("Y-m-d"))));
		$this->datbi = date('Y-m-d', strtotime('+1 days', strtotime(date("Y-m-d"))));
	}

}




/**
 * ListTransaction sonucunda dönecek nesne
 * @author proje
 *
 */
class TransactionListResponse extends fillFromJson_
{
	public $companyCode;
	public $companyName;

	public $total;
	public $total_count;

	public $limit;
	public $ending_before;
	public $starting_after;
	public $object_name;
	public $has_more;

	public $Data = array('TransactionListData');
}




/**
 * TransactionListResponse'daki data dizisinin satırlar
 * @author proje
 *
 */
class TransactionListData extends fillFromJson_
{
	public $companyCode;
	public $companyName;
	public $agent_id;
	public $agent_referans_no;
	public $agent_name;
	public $xact_id;
	public $xact_date;
	public $is_tds;
	public $bank_id;
	public $bank_name;
	public $card_no;
	public $card_holder;
	public $card_type;
	public $card_type_name;
	public $authorization_code;
	public $reference_code;
	public $order_id;
	public $postype_desc;
	public $xact_type;
	public $xacttype_desc;
	public $fiscal_period_id;
	public $sector_id;
	public $sectorid_desc;
	public $merchant_id;
	public $channel_name;
	public $ipaddress;
	public $client_id;
	public $xact_transaction_id;
	public $terminal_id;

	public $is_succeed;
	public $reversed;
	public $is_reconcile;
	public $is_payup;
	public $is_onchargeback;
	public $is_transferred;

	public $reversed_xact_id;
	public $channel_id;
	public $pos_type;
	public $instalment;

	public $amount;
	public $net_amount;
	public $comission;
	public $comission_tax;
	public $currency;
	public $ratio;
	public $end_user_comission;

	public $user_id;
	public $xact_time;
	public $xact_note;
	public $xact_agent_reference;
	public $company_pay_ratio;

	public $company_cost_ratio;

	public $ana_firma_brut_alacak;
	public $ana_firma_komisyonu;
	public $ana_firma_komisyonu_kdv_dahil;
	public $ana_firma_odenecek_net_tutar;
	public $bayi_brut_alacak;
	public $bayi_komisyonu;
	public $bayi_komisyonu_kdv_dahil;
	public $bayiye_odenecek_net_tutar;

	public $cp_mfi_vdate;
	public $cp_mfi_vdate_day;
	public $ap_mfi_vdate;
	public $ap_mfi_vdate_day;

}



/**
 * MarkTransferred() metodu için parametreler
 * @author proje
 *
 */
class MarkTransferParameters
{
	public $xact_id;
	public $document_no;
	public $amount;
	public $currency;
	public $exchange_rate;
}




/**
 * CreateMailOrder() metodunun parametreleri
 * @author proje
 *
 */

class MailOrderParameters
{
	public $pos_type;
	public $addcomission_to_amount;
	public $agent_id;
	public $name_surname;
	public $user_name;
	public $amount;
	public $email;
	public $send_mail;
	public $phone;
	public $send_sms;
	public $expire_date;
	public $note;
	public $agent_note;
	public $reference_no;
	public $succeed_url;
	public $error_url;
	public $confirmation_url;
	public $send_confirmation_mail;
	public $multi_payment;


	public function __construct()
	{
		$this->pos_type = 5;
		$this->addcomission_to_amount = true;
		$this->multi_payment = true;
		$this->send_confirmation_email = true;
		$this->send_mail = false;
		$this->send_sms = false;
		$this->phone = "";
		$this->email = "";
		$this->succeed_url = "";
		$this->error_url = "";
		$this->confirmation_url = "";
		$this->expire_date = 24;
	}
}






/**
 * CreateMailOrder() metodunun sonucu
 * @author proje
 *
 */
class MailOrderResult extends Result
{
	public $url;
}






/**
 * ReversedRequest() için parametreler
 * @author agitk
 *
 */
class ReversedRequestParameters
{
	public $xact_id;
	public $amount;
	public $succeedUrl;
}





/**
 * ReversedRequest() metodundan dönecek nesne, bu nesne Result ile aynı içerikli olduğu için (şimdilik) ekleme yapmaya gerek yok
 * @author proje
 *
 */
class ReversedRequestResponse extends Result
{
}










/**
 * Autologin() metodunun parametresi
 * @author proje
 *
 */
class AutologinParameters
{
	public $userName;
	public $agentID;
}









/**
 * Autologin() metodunun dönüş nesnesi
 * @author proje
 *
 */
class AutologinResult extends Result
{
	public $url;
}



/**
 * Hata oluştuğunda dönecek nesne
 * @author proje
 *
 */
class PaynetException extends Exception
{
}

class PaynetTools {

	public static function FormatWithDecimalSeperator($amount)
	{
		 $amount = number_format($amount, 2, ',', '');
		 return $amount;
	}

	public static function FormatWithoutDecimalSeperator($amount)
	{
		$amount = round($amount*100);
	    return $amount;
	}

	public static function getInstallmentsHtml($ratioData)
    {

		$uniqueInstallments = array();

        foreach ($ratioData as $bank)
		{
			foreach ($bank->ratio as $ratio)
			{
				if(!in_array($ratio->instalment, $uniqueInstallments))
				{
					array_push($uniqueInstallments, $ratio->instalment);
				}
			}
		}

		sort($uniqueInstallments);

		$resultHtml = '<table class="paynet-ratio-table">
									<thead>
										<tr><th></th>';

		foreach ($uniqueInstallments as $instalment)
		{
			$resultHtml .= '<th>'.$instalment.'</th>';
		}

		$resultHtml .='</thead><tbody>';

		foreach ($ratioData as $bank)
		{
			$resultHtml .= '<tr><td><img src="'. $bank->bank_logo .'"></td>';

			foreach ($uniqueInstallments as $instalment)
			{
				$thisRatioItem = null;

				foreach ($bank->ratio as $ratioItem)
				{
					if($ratioItem->instalment==$instalment)
					{
						$thisRatioItem = $ratioItem;
					}
				}

				if($thisRatioItem == null)
				{
					$resultHtml .= '<td>-</td>';
				}
				else
				{
					$resultHtml .= '<td>'. number_format($thisRatioItem->ratio * 100, 2, '.', '') .'</td>';
				}
			}
		}

		return $resultHtml;

    }

}

?>