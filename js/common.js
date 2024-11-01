jQuery(document).ready(function () {
    if (document.getElementById('qrcode')) {
        var qrCodeUrl = jQuery('#qrcode').data('qr-url');
        new QRCode(document.getElementById("qrcode"), qrCodeUrl);
    }
});

var unsOpenedWindow;

function openUNSWindow(url){
    var unsPopupWidth  = 600;
    var unsPopupHeight = 600;
    var unsPopupLeft   = (screen.width - unsPopupWidth) / 2;
    var unsPopupTop    = (screen.height - unsPopupHeight) / 2;
    var unsPopupParams = 'width='+ unsPopupWidth+', height='+unsPopupHeight;
    unsPopupParams += ', top='+unsPopupTop+', left='+unsPopupLeft;
    unsPopupParams += ', menubar=no';
    unsPopupParams += ', resizable=yes';
    unsPopupParams += ', scrollbars=yes';
    unsPopupParams += ', status=no';
    unsPopupParams += ', toolbar=no';
    return unsOpenedWindow = window.open(url,'unsproject-main-window', unsPopupParams);
}

function openLoadingWindow(){
    unsOpenedWindow = openUNSWindow('');
    unsOpenedWindow.onbeforeunload = function(){
       console.log("The window has been closed.");
    }
    unsOpenedWindow.document.write('<p style="text-align:center">Loading...</p><style>*{background-color:#e9e9e9;}</style>');
}

function closeUnsWindow(){
    if(unsOpenedWindow !== null){
       unsOpenedWindow.close();
    }
}
function checkConnection(url, serviceTicket, sessionId, page, uniqueId, authenticationInterval, guardianlink) {
    var unsCheckIfAjaxCompleted = null;
    var waitingForCallback = false;
    jQuery(document).ready(function () {

        var unsTimeout = setInterval(function () {
            unsCheckIfAjaxCompleted = false;
            jQuery.ajax({
                url: url,
                method: 'POST',
                data: {
                    serviceTicket: serviceTicket,
                    sessionId: sessionId,
                    timestamp: (new Date()).getTime(),
                },
                success: function (data) {
                    var json = JSON.parse(data);
                    if(json !== null && typeof json.guardianUrl !== 'undefined'){
                        if(
                            json.guardianUrl !== ''
                            && typeof json.callback !== 'undefined'
                            && json.callback === "1"
                            && waitingForCallback === false
                            && json.action === 'login-guardian'
                        ) {
                            unsCheckIfAjaxCompleted = null;
                            waitingForCallback = true;
                            unsOpenedWindow = openUNSWindow(json.guardianUrl);
                        }
                        return;
                    }
                    switch (page) {
                        case "0": //LOGIN
                            if (json !== null && typeof json.jwt !== 'undefined' && typeof json.action !== 'undefined') {
                                var unsForm = createUNSProjectLoginScreenForm(url, json, uniqueId)
                                document.body.appendChild(unsForm);
                                closeUnsWindow();
                                clearInterval(unsTimeout);
                                if(json.action === 'login') {
                                    unsForm.submit();
                                } else {
                                    jQuery('.uns-popup-register a.close').on('click',function(){
                                        jQuery('.uns-popup-register').remove();
                                        jQuery('.unsOverlay').remove();

                                    });
                                }
                            } else {
                                console.error('Unable to login due to invalid response');
                                //clearInterval(unsTimeout);
                            }
                            break;
                        case "1": //PROFILE
                        case "2": //ADMIN
                            if (json !== null && typeof json.jwt !== 'undefined') {
                                console.log('Account was successfully linked...');
                                closeUnsWindow();
                                location.reload();
                                // window.location.href = window.location.href;
                            }
                            break;
                    }
                    unsCheckIfAjaxCompleted = true;

                },
                error: function (request) {
                    var json = null;
                    if(request !== null && typeof request.responseText !== 'undefined'){
                        json = JSON.parse(request.responseText);
                    } else {
                        //clearInterval(unsTimeout);
                    }
                    if (json !== null && typeof json.data !== 'undefined' && json.data.errorCode !== 'undefined') {
                        if (json.errorCode > 0) {
                            //clearInterval(unsTimeout);
                            console.log('Error. We can not auto-login.');

                        } else {
                            jQuery('#unsproject-status').html('<div class="loader">Waiting for authorization<span>.</span><span>.</span><span>.</span>. <Br />Page will automatically reload once you are authorized.');
                        }
                    } else {
                        console.log('Something is wrong with the API.');
                        //clearInterval(unsTimeout);
                    }
                    unsCheckIfAjaxCompleted = true;
                }
            });
        }, authenticationInterval);
    });
}

function createUNSProjectLoginScreenForm(url, json, uniqueId) {
    var unsElement;
    var unsForm = document.createElement('form');
    unsForm.className='uns-popup-register ' + json.action;
    unsForm.action = url + ( json.action === 'register' ? '/register' : '/autologin');
    unsForm.method = 'POST';

    unsElement = document.createElement('input');
    unsElement.type = 'hidden';
    unsElement.name = 'jwt';
    unsElement.value = json.jwt;
    unsForm.appendChild(unsElement);

    if(json.action === 'register'){

        //Header
        var unsRegisterRowElement = document.createElement('div');
        unsRegisterRowElement.className = 'uns-register-header';

        unsElement = document.createElement('div');
        unsElement.className ='uns-register-icon';
        unsRegisterRowElement.appendChild(unsElement);

        unsElement = document.createElement('h2')
        unsElement.innerHTML = 'Success!';
        unsRegisterRowElement.appendChild(unsElement);

        unsElement = document.createElement('h3')
        unsElement.innerHTML = 'Your have been authenticated to UNS';
        unsRegisterRowElement.appendChild(unsElement);

        unsForm.appendChild(unsRegisterRowElement);

        //Content
        unsRegisterRowElement = document.createElement('div');
        unsRegisterRowElement.className = 'uns-register-content';

        unsElement = document.createElement('div');
        unsElement.className = 'unsRegisterTitle';
        unsElement.innerHTML = 'Continue your WordPress registration with <br />'+ uniqueId;
        unsRegisterRowElement.appendChild(unsElement);

        unsElement = document.createElement('div');
        unsElement.innerHTML = 'Please enter your preferred username:';
        unsElement.className = 'unsRegister-username-label';
        unsRegisterRowElement.appendChild(unsElement);

        //Username wrapper
        var unsCustomInput = document.createElement('div');
        unsCustomInput.className='uns-input-wrapper';

        unsElement = document.createElement('input');
        unsElement.type='text';
        unsElement.name='username';
        unsElement.required=true;
        unsElement.className = 'unsUsername';
        unsElement.placeholder='Username';
        unsCustomInput.appendChild(unsElement);

        unsElement = document.createElement('span');
        unsElement.className = 'uns-requied';
        unsElement.innerHTML = 'required';
        unsCustomInput.appendChild(unsElement);

        unsRegisterRowElement.appendChild(unsCustomInput);

        //Text Muted
        unsElement = document.createElement('div');
        unsElement.innerHTML = 'This cannot be changed later.';
        unsElement.className = 'unsTextMuted';
        unsRegisterRowElement.appendChild(unsElement);

        unsElement = document.createElement('div');
        unsElement.innerHTML = 'WordPress requires an email address to complete your registration';
        unsElement.className = 'unsRegister-email-label';
        unsRegisterRowElement.appendChild(unsElement);

        //Email wrapper
        unsCustomInput = document.createElement('div');
        unsCustomInput.className='uns-input-wrapper';

        unsElement = document.createElement('input');
        unsElement.type='text';
        unsElement.name='email';
        unsElement.required=true;
        unsElement.className = 'unsEmail';
        unsElement.placeholder='Email';
        unsCustomInput.appendChild(unsElement);

        unsElement = document.createElement('span');
        unsElement.className = 'uns-requied';
        unsElement.innerHTML = 'required';
        unsCustomInput.appendChild(unsElement);

        unsRegisterRowElement.appendChild(unsCustomInput);

        unsForm.appendChild(unsRegisterRowElement);

        //Submit
        unsElement = document.createElement('button');
        unsElement.type = 'submit';
        unsElement.className='button button-primary button-large';
        unsElement.textContent ='Complete WordPress Registration';
        unsForm.appendChild(unsElement);

        //Close
        unsElement = document.createElement('a');
        unsElement.href = 'javascript:void(0)';
        unsElement.title = 'Close';
        unsElement.className='unsicon-close';
        unsForm.appendChild(unsElement);

        unsElement = document.createElement('div');
        unsElement.className ='unsOverlay';
        unsElement.style="height:"+Math.max(
            document.body.scrollHeight,
            document.body.offsetHeight,
            document.body.clientHeight,
            document.body.scrollHeight,
            document.body.offsetHeight
        ) + 'px';
        document.body.appendChild(unsElement);

        document.body.scrollTop = 0;
    }

    return unsForm;
}

