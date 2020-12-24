@php
    $paynetStandard = app('Innovia\Paynet\Payment\Paynet');

@endphp


<body>
    <form action={{ $chargePostUrl }} method="post" name="checkout-form" id="checkout-form">
        {{  csrf_field() }}
           <script type="text/javascript"
                   class="paynet-button"
                   src="https://pts-pj.paynet.com.tr/public/js/paynet.js"
                   data-key="{{ $publicKey }}"
                   data-amount="{{ $amount }}"
                   data-image="http://icons.iconarchive.com/icons/stalker018/mmii-flat-vol-3/72/dictionary-icon.png"
                   data-button_label="Ödemeyi Tamamla"
                   data-description="Ödemenizi tamamlamak için bilgileri girip tamam butonuna basınız"
                   data-agent=""
                   data-add_commission_amount="false"
                   data-no_instalment="false"
                   data-tds_required="false"
                   data-pos_type="5">
           </script>

           <!--aşağıdaki değerler ödeme tamamlandıktan sonra DemoChargeServer.php sayfasına post edilecek-->
           <input type="hidden" id="musteri_referans" name="musterireferans" value="1234">
           <input type="hidden" id="musteri_referans2" name="musteri_referans2" value="1111">

     </form>
</body>


{{-- Hazir Formu cakalim buraya --}}
