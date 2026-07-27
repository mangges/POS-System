//Usage: - Add class "using-price-input" to the input field you want to format as Rupiah currency.
//       - Add Hidden input with input id e.g. if your input id is "cashReceived", add a hidden input with id "cashReceived_raw" to store the unformatted value.

window.formatPrice = function(value) {
    const angka = value.replace(/[^0-9]/g, '');
    if (angka === '') return '';
    return parseInt(angka, 10).toLocaleString('id-ID');
}

window.unformatPrice = function(value) {
    return parseInt(value.replace(/[^0-9]/g, ''), 10) || 0;
}

function selectCash(amount) {
    const parsedAmount = parseInt(amount, 10);
    const input = document.getElementById('cashReceived');
    if (!input) return;

    const current = parseInt(input.value.replace(/[^0-9]/g, '')) || 0;
    const newValue = current + parsedAmount;

    input.value = newValue.toLocaleString('id-ID');
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

function resetDenominations() {
    const input = document.getElementById('cashReceived');
    if (!input) return;
    input.value = '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

document.addEventListener('click', function (e) {
    const btn = e.target;
    
    if (btn.matches('[data-select-cash]')) {
        const amount = btn.getAttribute('data-select-cash');
        selectCash(amount);
    } else if (btn.matches('#resetDenominations')) {
        resetDenominations();
    } else {
        return;
    }
});

document.addEventListener('input', function (e) {
    if (!e.target.classList.contains('using-price-input')) return;

    const input = e.target;
    const cursorPos = input.selectionStart;
    const oldLength = input.value.length;

    input.value = formatPrice(input.value);

    const newLength = input.value.length;
    const diff = newLength - oldLength;
    input.selectionEnd = input.selectionStart = cursorPos + diff;

    const rawInput = document.getElementById(input.id + '_raw');
    if (rawInput) {
        rawInput.value = unformatPrice(input.value);
    }
});