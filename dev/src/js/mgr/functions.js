//функция отправки ajax для редактирования заказа
export function sendAjax(params, callback, url, headers, args = {}, method = 'POST') {
    if(args.rows_wrapper){
        args.rows_wrapper.classList.add('loading');
    }
    headers = headers || {"X-Requested-With": "XMLHttpRequest"};
    let options = {
        method: method,
        headers: headers,
        body: params
    };

    fetch(url, options)
        .then(response => response.json())
        .then(result => callback(result, args));
}

export function showNotify(msg, type = 'success',  timeout = 2000){
    if(typeof Swal !== "undefined"){
        const Toast = Swal.mixin({
            toast: true,
            position: 'top',
            showConfirmButton: false,
            timer: timeout,
        })

        Toast.fire({
            icon: type,
            title: msg,
        });
    }
}

export function confirmModal(modal, values, selectors){
    if(selectors){
        for(let s in selectors){
            let elems = modal.querySelectorAll(s);
            if(elems.length){
                elems.forEach(el => el.innerHTML = selectors[s]);
            }
        }
    }
    for (let k in values){
        let field = modal.querySelector('[name="'+k+'"]');
        if(field){
            field.value = values[k];
        }
    }
}

export function success(response,rows_wrapper,modals,selectorPrefix){
    if(rows_wrapper){
        rows_wrapper.classList.remove('loading');
    }

    if(modals){
        modals.forEach(modalEl => {
            let modal = bootstrap.Modal.getInstance(modalEl);
            if(modal)  modal.hide();
        });
    }
    if(response.object){
        if(response.object.html){
            rows_wrapper.innerHTML = response.object.html;
        }
        if(response.object.id){
            if(document.querySelector(selectorPrefix+response.object.id)){
                document.querySelector(selectorPrefix+response.object.id).remove();
            }
        }
    }
}

export function formatPrice(price, price_format, price_format_no_zeros) {
    const pf = price_format;
    price = numberFormat(price, pf[0], pf[1], pf[2]);

    if (price_format_no_zeros && pf[0] > 0) {
        price = price.replace(/(0+)$/, '');
        price = price.replace(/[^0-9]$/, '');
    }

    return price;
}

export function numberFormat(number, decimals, decPoint, thousandsSep) {
    // original by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
    // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // bugfix by: Michael White (http://crestidg.com)
    var i, j, kw, kd, km;

    // input sanitation & defaults
    if (isNaN(decimals = Math.abs(decimals))) {
        decimals = 2;
    }
    if (decPoint == undefined) {
        decPoint = ',';
    }
    if (thousandsSep == undefined) {
        thousandsSep = '.';
    }

    i = parseInt(number = (+number || 0).toFixed(decimals)) + '';

    if ((j = i.length) > 3) {
        j = j % 3;
    } else {
        j = 0;
    }

    km = j
        ? i.substring(0, j) + thousandsSep
        : '';
    kw = i.substring(j).replace(/(\d{3})(?=\d)/g, "$1" + thousandsSep);
    kd = (decimals
        ? decPoint + Math.abs(number - i).toFixed(decimals).replace(/-/, '0').slice(2)
        : '');

    return km + kw + kd;
}