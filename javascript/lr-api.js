jQuery(document).ready(function () {

    if (document.getElementById(SLI_RECOMMENDATIONS_DIV_ID) != null) {

        var cookieDomain = document.domain.replace(/^[^\.]+\./, "");
        var cookieSuffix = fletcher32(cookieDomain);

        var previousSessionId = getSliBeaconSessionId(cookieSuffix);

        var sidString = "";
        if (previousSessionId != "") {
            sidString = "&sn=" + previousSessionId;
        }

        var jsonpCallback = "recommendationsResponse";
        if (typeof SLI_JSONP_CALLBACK !== 'undefined') {
            jsonpCallback = SLI_JSONP_CALLBACK;
        }

        var skuString = "";
        if (typeof SLI_STRATEGY_INPUT_SKUS !== 'undefined') {
            for (sku in SLI_STRATEGY_INPUT_SKUS) {
                skuString += "&sku=" + sku;
            }
        }

        var attrString = "";
        if (typeof SLI_PRODUCT_ATTRIBUTES !== 'undefined') {
            attrString = "&att=" + SLI_PRODUCT_ATTRIBUTES.join("&att=");
        }

        jQuery.ajax({
            url: 'http://' + SLI_CLIENT_ID + '-' + SLI_SITE_ID + '.sli-r.com/r-api/1/r.jsonp?sid=' + SLI_STRATEGY_ID + '&c=' + SLI_RECOMMENDATION_REQUEST_COUNT + attrString + skuString + '&cb=' + jsonpCallback + sidString,
            dataType: 'jsonp',
            jsonpCallback: jsonpCallback,
            success: function (recommendationsData) {
                var text = '';
                var len = recommendationsData.results.length;
                if (previousSessionId == "") {
                    // set our own beacon cookie with the LR-generated session ID
                    setBeaconCookies(cookieSuffix, recommendationsData.sessionId, cookieDomain);
                } else {
                    // increment the view counter for SLI1 & SLI2 cookies
                    incrementBeaconCookies(cookieSuffix, recommendationsData.sessionId, cookieDomain);
                }
                for (var i = 0; i < len; i++) {
                    if (i == 0) {
                        text += "<div><strong>You may also like:</strong></div>\n";
                    }
                    recommendationsEntry = recommendationsData.results[i];
                    price = "";
                    if (typeof recommendationsEntry.price !== 'undefined') {
                        price = "$" + recommendationsEntry.price.toFixed(2);
                    }
                    text += '<div class="recommendation"><a href="' + recommendationsEntry.url + '"><img src = "' + recommendationsEntry.image + '"/><br />' + recommendationsEntry.title + '</a><br />' + price + '</div>'
                }
                jQuery('#' + SLI_RECOMMENDATIONS_DIV_ID).html(text);
            }
        });
    }
});

function getCookie(cname) {
    var name = cname + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') c = c.substring(1);
        if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
        }
    }
    return "";
}

function getSliBeaconSessionId(suffix) {
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') c = c.substring(1);
        if (c.indexOf("SLI1" + suffix) == 0) {
            return c.substring(c.indexOf("=") + 1, c.length);
        }
    }
    return "";
}

function setBeaconCookies(suffix, sessionId, cookieDomain) {
    var array = sessionId.split('.');
    setCookie("SLIBeacon" + suffix, array[0], 2 * 365 * 24 * 60, cookieDomain); // 2 years
    setCookie("SLI1" + suffix, sessionId, 30, cookieDomain); // 30 min
    setCookie("SLI2" + suffix, sessionId, 0, cookieDomain); // session cookie
    setCookie("SLI4" + suffix, array[1], 6 * 30 * 24 * 60, cookieDomain); // 6 months
}

function incrementBeaconCookies(suffix, sessionId, cookieDomain) {
    var sessionArray = sessionId.split('.');
    sessionArray[2]++;
    var newCookieValue = sessionArray.join('.');
    setCookie("SLI1" + suffix, newCookieValue, 30, cookieDomain); // 30 min
    setCookie("SLI2" + suffix, newCookieValue, 0, cookieDomain); // session cookie
}

function setCookie(cname, cvalue, exminutes, domain) {
    var expires = "";
    if (exminutes > 0) {
        var d = new Date();
        d.setTime(d.getTime() + (exminutes * 60 * 1000));
        expires = "expires=" + d.toUTCString() + "; ";
    }
    document.cookie = cname + "=" + cvalue + "; " + expires + "domain=" + domain + ";path=/";
}

// simplified version of fletcher32 since does not have to work with long strings
function fletcher32(str) {
    var sum1 = 0xffff, sum2 = 0xffff;
    for (var i = 0, iTop = str.length; i < iTop; i++) {
        sum2 += sum1 += str.charCodeAt(i);
    }
    sum1 = (sum1 & 0xffff) + (sum1 >> 16);
    sum2 = (sum2 & 0xffff) + (sum2 >> 16);
    return '_' + Math.abs(sum2 << 16 | sum1);
}