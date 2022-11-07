@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-12">
            <div class="card h-100">
                <div class="card-content collapse show">
                    <div class="card-body card-dashboard">
                        <div class="text-center my-2">
                        <h2> Two-Factor Authentication</h2>
                        <span> ( Use Google Authenticator App to scan QR Code and then put MFA code here)</span>
                        </div>
                        <div class="text-center qr-div">
                            {!! $qrCode !!}
                        </div>
                        <div class="text-center">
                            <button type="button"  class="btn btn-secondary toggle-qr-btn">Hide QR</button>
                            <fieldset class="mt-2">
                                <input type="checkbox" class="chk-mfa">
                                <label for="remember-me"> MFA Enabled </label>
                            </fieldset>
                            </div>
                                <form class="d-flex justify-content-center mt-2 text-center">
                                    <div class="col-md-3 me-2">
                                        <div class="form-group">
                                            <input type="password" class="form-control code" name="code" placeholder="Enter Google Authenticator Code" >
                                            <input type="hidden" class="form-control secret" name="secret" value="{{ $secret }}">
                                        </div>
                                    </div>
                                    <button type="button" autofocus class="btn btn-secondary verify-code-btn">Verify Code</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gasparesganga-jquery-loading-overlay@2.1.6/dist/loadingoverlay.min.js"></script>
<script>
   
    $(document).ready(function(){
        $.LoadingOverlay("show");
        setTimeout(function(){ 
            let QRDivContent = $('.qr-div a').html();
            if(QRDivContent != ""){
                $('.qr-div').html(QRDivContent);
            }
        }, 1000);

        $("[name='code']").keypress(function (e) {
            var key = e.which;
            if(key == 13) 
            {
                e.preventDefault();
                $('.verify-code-btn').click();
            }
        }); 

       setTimeout(function(){$.LoadingOverlay("hide");},2000);
       let mfaEnabled = "{{ $mfaEnabled }}";
       console.log('mfaEnabled : ' , mfaEnabled);
       if(mfaEnabled == 'Y'){
           $('.chk-mfa').prop('checked', true)
       } else {
          $('.chk-mfa').prop('checked', false)
       }
    });

    $('.code').keyup(function(e) {
        if (/\D/g.test(this.value)){
            this.value = this.value.replace(/\D/g, '');
        }
    });

    $(document).on('click','.toggle-qr-btn',function(){
        let element = $('.qr-div');
        let classExists = element.hasClass('d-none');
        element.toggleClass('d-none');
        if(classExists){
            $(this).text('Hide QR');
        } else {
            $(this).text('Show QR');
        }
    });

    $(document).on('click','.verify-code-btn',function(e){
        e.preventDefault();
        let codeSelector = $('.code');
        let code = codeSelector.val();
        let secret = $('.secret').val();
        let mfaEnabled = $('.chk-mfa').is(':checked');
        mfaEnabled = (mfaEnabled == 0 ? 'N': 'Y');
        if(codeSelector.val() == "" || code.length != 6){
            alert("Please enter 6 digits code");
            codeSelector.addClass('border-red');
        } else {
            codeSelector.removeClass('border-red');
            $.LoadingOverlay("show");
            $.ajax({
                url: "{{ route('verifyMFACode')}}",
                type: 'POST',
                data: {
                    'code': code,
                    'secret': secret,
                    'mfaEnabled': mfaEnabled,
                    '_token' : "{{ csrf_token() }}"
                },
                headers: {
                    'Accept' : 'application/json',
                    'Content-Type' : 'application/x-www-form-urlencoded'
                },
                success: function(data){
                if(data.status){
                    mfaEnabled  = data.mfa_enabled;
                    let checked = (mfaEnabled == 'N' ? false: true);  
                    $('.chk-mfa').attr('checked',checked);
                    codeSelector.val('');
                    alert(data.msg);

                } else {
                    alert(data.msg);
                }
                $.LoadingOverlay("hide");
                },
                error : function(data){
                    $.LoadingOverlay("hide");
                    alert('Whoops, looks like something went wrong');
                }
            });
        }
    });
</script>
@section('script')