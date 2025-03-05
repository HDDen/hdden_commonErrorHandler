/**
 * Принимает данные, пытается разобрать их и отправить на бэкенд
 * Вернет true в случае попытки отправки, false в случае неудачи отправки/некорректных данных
 * После удачной отправки сработает коллбэк
 * @param {*} data 
 * @returns 
 */
function hdden_commonErrorHandler(data){
    var debug = true;

    try {
        /**
         * Prepare data
         */
        var sendingData = new Object();
        if ((data instanceof Array) && data.length){
            sendingData.message = data.join("\r\n");
        } else if (typeof data === 'object'){
            if (data['message']) sendingData.message = data['message'];
            if (data['reason']) sendingData.reason = data['reason'];
        } else if ((typeof data === 'string') && data){
            sendingData.message = data;
        }

        if (Object.keys(sendingData).length < 1){
            console.log('hdden_commonErrorHandler(): sendingData is empty', sendingData);
            return false;
        }

        /**
         * Send to handler
         */
        var ajaxUrl = '/wp-admin/admin-ajax.php';
        if (window['hdden_commonErrorHandler_ajaxUrl'] && window['hdden_commonErrorHandler_ajaxUrl']['ajaxUrl']){
            ajaxUrl = window.hdden_commonErrorHandler_ajaxUrl.ajaxUrl;
        }

        // sending query
        var query = {
            'action': 'hdden_commonErrorHandler',
            'hdden_data': {
                'data': '',
            }
        };

        query.hdden_data.data = JSON.stringify(sendingData);

        /**
         * Data conversion to x-www-form-urlencoded
         */
        var xhr_query = [];
        Object.keys(query.hdden_data).forEach(function(element){
            xhr_query.push( 
                'hdden_data['+encodeURIComponent(element) + "]=" + encodeURIComponent(query.hdden_data[element])
            )
        });
        var xhr_body = xhr_query.join("&");
        // add action-param
        xhr_body = 'action='+query.action+'&'+xhr_body;

        /**
         * Sending
         */
        var xhr = new XMLHttpRequest();
        
        xhr.onload = function (){
            var data = JSON.parse(this.responseText);
            debug ? console.log('hdden_commonErrorHandler: ajax-answer', data) : '';
        }

        xhr.onerror = function (){
            debug ? console.log('hdden_commonErrorHandler: sending error', data) : '';
        }

        xhr.open("POST", ajaxUrl, true);
        xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xhr.send(xhr_body);
        return true;
    } catch (error) {
        console.log('hdden_commonErrorHandler()', error);
        return false;
    }
}