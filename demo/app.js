// ──────────────────────────────────────
// Arpay Demo — Frontend
// ──────────────────────────────────────

let currentGateway = 'paytr';
let currentTab = 'pay';

const tabTitles = {
    pay: 'Ödeme İşlemi', refund: 'İade İşlemi', query: 'İşlem Sorgulama',
    '3dsecure': '3D Secure Başlatma', subscription: 'Abonelik Oluşturma', installment: 'Taksit Sorgulama'
};
const submitLabels = {
    pay: 'Ödeme Yap', refund: 'İade Et', query: 'Sorgula',
    '3dsecure': '3D Secure Başlat', subscription: 'Abonelik Oluştur', installment: 'Taksitleri Sorgula'
};

// ──────────────────────────────────────
// GATEWAY SEÇİMİ
// ──────────────────────────────────────
function selectGateway(key) {
    currentGateway = key;
    document.querySelectorAll('.gateway-card').forEach(el => {
        el.classList.remove('border-primary-500', 'bg-primary-50');
        el.classList.add('border-gray-100', 'bg-white');
    });
    const active = document.getElementById('gw-' + key);
    active.classList.remove('border-gray-100', 'bg-white');
    active.classList.add('border-primary-500', 'bg-primary-50');

    document.getElementById('gateway-label').textContent = gatewayNames[key];
    updateTabs();
}

// ──────────────────────────────────────
// TAB SEÇİMİ
// ──────────────────────────────────────
function selectTab(tab) {
    const gf = features[currentGateway] || [];
    if (!gf.includes(tab)) return;

    currentTab = tab;
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');

    document.querySelectorAll('.tab-form').forEach(el => el.classList.add('hidden'));
    document.getElementById('form-' + tab).classList.remove('hidden');

    document.getElementById('tab-title').textContent = tabTitles[tab] || tab;
    document.getElementById('submit-label').textContent = submitLabels[tab] || 'Gönder';
}

function updateTabs() {
    const gf = features[currentGateway] || [];
    document.querySelectorAll('.tab-btn').forEach(btn => {
        const tab = btn.dataset.tab;
        btn.disabled = !gf.includes(tab);
        if (btn.disabled && currentTab === tab) {
            selectTab(gf[0] || 'pay');
        }
    });
}

// ──────────────────────────────────────
// FORM GÖNDER
// ──────────────────────────────────────
async function submitForm() {
    const btn = document.getElementById('submit-btn');
    const loading = document.getElementById('loading');
    btn.disabled = true;
    btn.classList.add('opacity-50');
    loading.classList.remove('hidden');

    const formEl = document.getElementById('form-' + currentTab);
    const inputs = formEl.querySelectorAll('input, select, textarea');
    const formData = new FormData();

    formData.append('action', currentTab);
    formData.append('gateway', currentGateway);
    formData.append('simulate_fail', document.getElementById('simulate-fail').checked ? '1' : '0');

    inputs.forEach(inp => {
        if (inp.name) formData.append(inp.name, inp.value);
    });

    try {
        const resp = await fetch(window.location.href, { method: 'POST', body: formData });
        const data = await resp.json();
        renderResult(data);
    } catch (err) {
        renderResult({ error: true, message: err.message });
    } finally {
        btn.disabled = false;
        btn.classList.remove('opacity-50');
        loading.classList.add('hidden');
    }
}

// ──────────────────────────────────────
// SONUÇ RENDER
// ──────────────────────────────────────
function renderResult(data) {
    const panel = document.getElementById('result-panel');
    panel.classList.remove('hidden');
    panel.classList.add('fade-in');

    const statusBar = document.getElementById('result-status');
    const statusIcon = document.getElementById('status-icon');
    const statusText = document.getElementById('status-text');
    const statusTime = document.getElementById('status-time');

    const isOk = data.success && !data.error;

    statusBar.className = 'rounded-t-xl px-5 py-3 flex items-center gap-3 ' + (isOk ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800');
    statusIcon.className = 'w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold ' + (isOk ? 'bg-green-500' : 'bg-red-500');
    statusIcon.innerHTML = isOk ? '✓' : '✗';
    statusText.textContent = isOk
        ? `${data.gateway || ''} — İşlem Başarılı`
        : `${data.gateway || ''} — İşlem Başarısız`;
    statusTime.textContent = data.timestamp || new Date().toLocaleString('tr-TR');

    // Data cards
    const cardsEl = document.getElementById('result-cards');
    cardsEl.innerHTML = '';

    if (data.data) {
        Object.entries(data.data).forEach(([key, val]) => {
            if (val === '' || val === null || val === undefined) return;
            if (typeof val === 'object') return; // skip arrays/objects for cards
            const card = document.createElement('div');
            card.className = 'bg-gray-50 rounded-lg p-3 border border-gray-100';
            const label = key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            card.innerHTML = `<div class="text-[10px] text-gray-400 uppercase font-semibold tracking-wider">${label}</div>
                              <div class="text-sm font-semibold mt-0.5 font-mono break-all">${val}</div>`;
            cardsEl.appendChild(card);
        });

        // Installment table
        if (data.data.installments && data.data.installments.length) {
            const wrapper = document.createElement('div');
            wrapper.className = 'col-span-2 md:col-span-4';
            let html = '<table class="w-full text-sm border border-gray-100 rounded-lg overflow-hidden"><thead class="bg-gray-50"><tr>';
            html += '<th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">Taksit</th>';
            html += '<th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">Taksit Tutarı</th>';
            html += '<th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">Toplam</th>';
            html += '<th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">Faiz</th>';
            html += '</tr></thead><tbody>';
            data.data.installments.forEach(inst => {
                html += `<tr class="border-t border-gray-50">
                    <td class="px-3 py-2 font-mono">${inst.count}x</td>
                    <td class="px-3 py-2 font-mono">${inst.per_installment.toFixed(2)} ₺</td>
                    <td class="px-3 py-2 font-mono">${inst.total.toFixed(2)} ₺</td>
                    <td class="px-3 py-2 font-mono">${inst.interest_rate.toFixed(2)}%</td>
                </tr>`;
            });
            html += '</tbody></table>';
            wrapper.innerHTML = html;
            cardsEl.appendChild(wrapper);
        }
    }

    if (data.error && data.message) {
        const errCard = document.createElement('div');
        errCard.className = 'col-span-2 md:col-span-4 bg-red-50 border border-red-100 rounded-lg p-3';
        errCard.innerHTML = `<div class="text-xs text-red-400 font-semibold uppercase">Hata</div>
                             <div class="text-sm text-red-700 mt-1">${data.message}</div>`;
        cardsEl.appendChild(errCard);
    }

    // Raw JSON
    document.getElementById('raw-json').textContent = JSON.stringify(data.raw || data, null, 2);

    // Code example
    document.getElementById('code-example').textContent = generateCode(data);

    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ──────────────────────────────────────
// KOD ÖRNEĞİ ÜRET
// ──────────────────────────────────────
function generateCode(data) {
    const gw = currentGateway;
    const gwName = gatewayNames[gw] || gw;
    const action = currentTab;
    const PHP = '<' + '?php';
    const NL = '\n';
    const BS = '\\';

    let code = PHP + NL + NL + 'use Arpay' + BS + 'Arpay;' + NL;

    const configs = {
        paytr:    "    'merchant_id'   => 'YOUR_MERCHANT_ID'," + NL + "    'merchant_key'  => 'YOUR_MERCHANT_KEY'," + NL + "    'merchant_salt' => 'YOUR_MERCHANT_SALT',",
        iyzico:   "    'api_key'    => 'YOUR_API_KEY'," + NL + "    'secret_key' => 'YOUR_SECRET_KEY',",
        vepara:   "    'api_key'     => 'YOUR_API_KEY'," + NL + "    'secret_key'  => 'YOUR_SECRET_KEY'," + NL + "    'merchant_id' => 'YOUR_MERCHANT_ID',",
        parampos: "    'client_code'     => 'YOUR_CLIENT_CODE'," + NL + "    'client_username' => 'YOUR_USERNAME'," + NL + "    'client_password' => 'YOUR_PASSWORD'," + NL + "    'guid'            => 'YOUR_GUID',",
        ipara:    "    'public_key'  => 'YOUR_PUBLIC_KEY'," + NL + "    'private_key' => 'YOUR_PRIVATE_KEY',",
        odeal:    "    'api_key'    => 'YOUR_API_KEY'," + NL + "    'secret_key' => 'YOUR_SECRET_KEY',",
        paynet:   "    'secret_key'  => 'YOUR_SECRET_KEY'," + NL + "    'merchant_id' => 'YOUR_MERCHANT_ID',",
        payu:     "    'merchant'   => 'YOUR_MERCHANT'," + NL + "    'secret_key' => 'YOUR_SECRET_KEY',",
        papara:   "    'api_key'     => 'YOUR_API_KEY'," + NL + "    'merchant_id' => 'YOUR_MERCHANT_ID',",
    };

    code += NL + '// ' + gwName + ' gateway oluştur' + NL;
    code += "$gateway = Arpay::create('" + gw + "', [" + NL + (configs[gw] || '') + NL + "    'test_mode' => true," + NL + "]);" + NL + NL;

    if (action === 'pay') {
        code += 'use Arpay' + BS + 'DTO' + BS + 'CreditCard;' + NL;
        code += 'use Arpay' + BS + 'DTO' + BS + 'Customer;' + NL;
        code += 'use Arpay' + BS + 'DTO' + BS + 'PaymentRequest;' + NL;
        code += 'use Arpay' + BS + 'DTO' + BS + 'CartItem;' + NL + NL;
        code += "$card = CreditCard::create('Ad Soyad', '4111111111111111', '12', '2028', '123');" + NL;
        code += "$customer = Customer::create('Ad', 'Soyad', 'email@example.com', '05551234567');" + NL + NL;
        code += '$request = PaymentRequest::create()' + NL;
        code += '    ->amount(150.00)' + NL;
        code += "    ->currency('TRY')" + NL;
        code += "    ->orderId('ORD_' . uniqid())" + NL;
        code += "    ->description('Ödeme açıklaması')" + NL;
        code += '    ->card($card)' + NL;
        code += '    ->customer($customer)' + NL;
        code += "    ->addCartItem(CartItem::create('ITEM_1', 'Ürün', 'Genel', 150.00));" + NL + NL;
        code += '$response = $gateway->pay($request);' + NL + NL;
        code += 'if ($response->isSuccessful()) {' + NL;
        code += '    echo "Ödeme başarılı! İşlem ID: " . $response->getTransactionId();' + NL;
        code += '} else {' + NL;
        code += '    echo "Hata: " . $response->getErrorMessage();' + NL;
        code += '}' + NL;
    } else if (action === 'refund') {
        code += 'use Arpay' + BS + 'DTO' + BS + 'RefundRequest;' + NL + NL;
        code += '$request = RefundRequest::create()' + NL;
        code += "    ->transactionId('TXN_XXX')" + NL;
        code += '    ->amount(50.00)' + NL;
        code += "    ->reason('Müşteri talebi');" + NL + NL;
        code += '$response = $gateway->refund($request);' + NL + NL;
        code += 'if ($response->isSuccessful()) {' + NL;
        code += '    echo "İade başarılı! Tutar: " . $response->getRefundedAmount();' + NL;
        code += '} else {' + NL;
        code += '    echo "Hata: " . $response->getErrorMessage();' + NL;
        code += '}' + NL;
    } else if (action === 'query') {
        code += 'use Arpay' + BS + 'DTO' + BS + 'QueryRequest;' + NL + NL;
        code += '$request = QueryRequest::create()' + NL;
        code += "    ->transactionId('TXN_XXX');" + NL + NL;
        code += '$response = $gateway->query($request);' + NL + NL;
        code += 'if ($response->isSuccessful()) {' + NL;
        code += '    echo "Durum: " . $response->getPaymentStatus()->value;' + NL;
        code += '} else {' + NL;
        code += '    echo "Hata: " . $response->getErrorMessage();' + NL;
        code += '}' + NL;
    } else if (action === '3dsecure') {
        code += 'use Arpay' + BS + 'DTO' + BS + 'CreditCard;' + NL;
        code += 'use Arpay' + BS + 'DTO' + BS + 'Customer;' + NL;
        code += 'use Arpay' + BS + 'DTO' + BS + 'SecurePaymentRequest;' + NL;
        code += 'use Arpay' + BS + 'DTO' + BS + 'CartItem;' + NL;
        code += 'use Arpay' + BS + 'DTO' + BS + 'SecureCallbackData;' + NL + NL;
        code += "$card = CreditCard::create('Ad Soyad', '4111111111111111', '12', '2028', '123');" + NL;
        code += "$customer = Customer::create('Ad', 'Soyad', 'email@example.com');" + NL + NL;
        code += '$request = SecurePaymentRequest::create()' + NL;
        code += '    ->amount(150.00)' + NL;
        code += "    ->currency('TRY')" + NL;
        code += "    ->orderId('ORD_3D_' . uniqid())" + NL;
        code += '    ->card($card)' + NL;
        code += '    ->customer($customer)' + NL;
        code += "    ->callbackUrl('https://siteadresiniz.com/callback')" + NL;
        code += "    ->successUrl('https://siteadresiniz.com/success')" + NL;
        code += "    ->failUrl('https://siteadresiniz.com/fail')" + NL;
        code += "    ->addCartItem(CartItem::create('ITEM_1', 'Ürün', 'Genel', 150.00));" + NL + NL;
        code += '// 1. 3D Secure başlat' + NL;
        code += '$initResponse = $gateway->initSecurePayment($request);' + NL + NL;
        code += 'if ($initResponse->isRedirectRequired()) {' + NL;
        code += '    // Kullanıcıyı banka sayfasına yönlendir' + NL;
        code += "    header('Location: ' . $initResponse->getRedirectUrl());" + NL;
        code += '    exit;' + NL;
        code += '}' + NL + NL;
        code += "// 2. Callback'te ödemeyi tamamla" + NL;
        code += '$callbackData = SecureCallbackData::fromRequest($_POST);' + NL;
        code += '$response = $gateway->completeSecurePayment($callbackData);' + NL;
    } else if (action === 'subscription') {
        code += 'use Arpay' + BS + 'DTO' + BS + 'CreditCard;' + NL;
        code += 'use Arpay' + BS + 'DTO' + BS + 'Customer;' + NL;
        code += 'use Arpay' + BS + 'DTO' + BS + 'SubscriptionRequest;' + NL + NL;
        code += "$card = CreditCard::create('Ad Soyad', '4111111111111111', '12', '2028', '123');" + NL;
        code += "$customer = Customer::create('Ad', 'Soyad', 'email@example.com');" + NL + NL;
        code += '$request = SubscriptionRequest::create()' + NL;
        code += "    ->planName('Premium Aylık')" + NL;
        code += '    ->amount(99.99)' + NL;
        code += "    ->currency('TRY')" + NL;
        code += "    ->period('monthly')" + NL;
        code += '    ->periodInterval(1)' + NL;
        code += '    ->card($card)' + NL;
        code += '    ->customer($customer);' + NL + NL;
        code += '$response = $gateway->createSubscription($request);' + NL + NL;
        code += 'if ($response->isSuccessful()) {' + NL;
        code += '    echo "Abonelik oluşturuldu! ID: " . $response->getSubscriptionId();' + NL;
        code += '} else {' + NL;
        code += '    echo "Hata: " . $response->getErrorMessage();' + NL;
        code += '}' + NL;
    } else if (action === 'installment') {
        code += NL + '// Taksit seçeneklerini sorgula' + NL;
        code += "$installments = $gateway->queryInstallments('411111', 150.00);" + NL + NL;
        code += 'foreach ($installments as $info) {' + NL;
        code += '    echo sprintf(' + NL;
        code += '        "%dx taksit: %.2f ₺/ay, toplam %.2f ₺ (%%.2f faiz)\\n",' + NL;
        code += '        $info->installmentCount,' + NL;
        code += '        $info->installmentAmount,' + NL;
        code += '        $info->totalAmount,' + NL;
        code += '        $info->interestRate' + NL;
        code += '    );' + NL;
        code += '}' + NL;
    }

    return code;
}

function copyCode() {
    const code = document.getElementById('code-example').textContent;
    navigator.clipboard.writeText(code).then(() => {
        const btn = event.target.closest('button');
        const orig = btn.innerHTML;
        btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Kopyalandı!';
        setTimeout(() => btn.innerHTML = orig, 1500);
    });
}

// Init
updateTabs();
