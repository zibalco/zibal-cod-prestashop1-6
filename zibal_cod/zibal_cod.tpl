
<!-- zibal.ir COD Payment Module -->
<p class="payment_module">
  <a href="javascript:$('#zibal_cod').submit();" class="zibal" title="COD Payment with zibal.ir">
    <img src="modules/zibal/logo.png" alt="COD Payment with zibal.ir" style="margin-left:20px;" />
 پرداخت در محل با زیبال
  </a>
</p>
<form id="zibal_cod" action="modules/zibal_cod/process.php?do=payment" method="post" class="hidden">
  <input type="hidden" name="orderId" value="{$orderId}" />
</form>
<!-- End of zibal.ir COD Payment Module-->

