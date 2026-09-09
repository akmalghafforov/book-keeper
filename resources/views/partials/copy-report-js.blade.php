<script>
    function isAndroidChrome() {
        const userAgent = navigator.userAgent || '';

        return /Android/i.test(userAgent) && /Chrome\//i.test(userAgent) && !/EdgA|OPR|Firefox/i.test(userAgent);
    }

    function blobToImage(blob) {
        return new Promise((resolve, reject) => {
            const image = new Image();
            const objectUrl = URL.createObjectURL(blob);

            image.onload = () => {
                URL.revokeObjectURL(objectUrl);
                resolve(image);
            };
            image.onerror = () => {
                URL.revokeObjectURL(objectUrl);
                reject(new Error('{{ __('Failed to prepare report image.') }}'));
            };
            image.src = objectUrl;
        });
    }

    async function convertBlobToClipboardPng(blob) {
        const image = await blobToImage(blob);
        const canvas = document.createElement('canvas');
        canvas.width = image.naturalWidth || image.width;
        canvas.height = image.naturalHeight || image.height;

        const context = canvas.getContext('2d', { alpha: false });
        if (!context) {
            throw new Error('{{ __('Failed to prepare report image.') }}');
        }

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0);

        return await new Promise((resolve, reject) => {
            canvas.toBlob((pngBlob) => {
                if (pngBlob) {
                    resolve(pngBlob);
                } else {
                    reject(new Error('{{ __('Failed to prepare report image.') }}'));
                }
            }, 'image/png');
        });
    }

    async function writeReportClipboard(imageBlob, whatsappText) {
        const pngBlob = await convertBlobToClipboardPng(imageBlob);
        const pngItem = { 'image/png': pngBlob };

        if (isAndroidChrome()) {
            await navigator.clipboard.write([new ClipboardItem(pngItem)]);

            return;
        }

        try {
            await navigator.clipboard.write([
                new ClipboardItem({
                    ...pngItem,
                    'text/plain': new Blob([whatsappText], { type: 'text/plain' }),
                }),
            ]);
        } catch (combinedError) {
            console.warn('Combined image/text copy failed, trying image only...', combinedError);
            await navigator.clipboard.write([new ClipboardItem(pngItem)]);
        }
    }

    async function copyReportToClipboard(button, reportName, reportUrl) {
        const originalHtml = button.innerHTML;
        const btnText = button.querySelector('.btn-text');
        
        // Extract client name from report name: "Debt Report: Client Name (Date)"
        let clientName = reportName;
        const match = reportName.match(/Debt Report: (.*) \(/);
        if (match && match[1]) {
            clientName = match[1];
        } else if (reportName.includes(':')) {
            clientName = reportName.split(':')[1].split('(')[0].trim();
        }

        console.log(clientName);
        

        const whatsappText = `{{ __('Report') }}: *${clientName}*`;

        // Ensure we use the current origin for fetch
        const fetchUrl = reportUrl.startsWith('http')
            ? reportUrl.replace(/^https?:\/\/[^\/]+/, window.location.origin)
            : reportUrl;

        try {
            button.disabled = true;
            if (btnText) btnText.innerText = '...';

            console.log('Fetching report from:', fetchUrl);
            const response = await fetch(fetchUrl);
            if (!response.ok) {
                console.error('Fetch failed:', response.status, response.statusText);
                throw new Error('{{ __('Failed to fetch report image.') }}');
            }
            const blob = await response.blob();
            console.log('Fetched blob:', blob.type, blob.size);

            if (navigator.clipboard && window.ClipboardItem) {
                await writeReportClipboard(blob, whatsappText);
                
                button.classList.remove('text-blue-600', 'dark:text-blue-400');
                button.classList.add('text-green-600', 'dark:text-green-400');
                if (btnText) btnText.innerText = '{{ __('Copied!') }}';

                setTimeout(() => {
                    button.innerHTML = originalHtml;
                    button.classList.add('text-blue-600', 'dark:text-blue-400');
                    button.classList.remove('text-green-600', 'dark:text-green-400');
                    button.disabled = false;
                }, 2000);
            } else {
                throw new Error('Clipboard API or ClipboardItem not supported/available (must be over HTTPS or localhost)');
            }
        } catch (error) {
            console.error('Final clipboard error:', error);
            alert('{{ __('Failed to copy to clipboard.') }} ' + error.message);
            button.innerHTML = originalHtml;
            button.disabled = false;
        }
    }
</script>

<div id="whatsapp-share-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="whatsapp-share-title">
    <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl dark:bg-[#1C1C1A]">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 id="whatsapp-share-title" class="text-lg font-bold text-gray-900 dark:text-white">{{ __('Share via WhatsApp') }}</h3>
                <p id="whatsapp-share-client" class="mt-1 text-sm text-gray-600 dark:text-gray-300"></p>
            </div>
            <button type="button" onclick="closeWhatsAppShare()" class="text-gray-500 hover:text-gray-900 dark:hover:text-white" aria-label="{{ __('Close') }}">&times;</button>
        </div>
        <label for="whatsapp-share-phone" class="mt-5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Phone Number') }}</label>
        <input id="whatsapp-share-phone" type="tel" inputmode="tel" class="mt-1 block w-full rounded-md border-gray-300 dark:border-[#3E3E3A] dark:bg-[#161615] dark:text-white" placeholder="992xxxxxxxxx">
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Include the country code to open a direct chat. You can leave this blank to choose a recipient in WhatsApp.') }}</p>
        <label for="whatsapp-share-message" class="mt-4 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Message') }}</label>
        <textarea id="whatsapp-share-message" rows="4" readonly class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 dark:border-[#3E3E3A] dark:bg-[#161615] dark:text-white"></textarea>
        <p id="whatsapp-share-error" class="mt-3 hidden text-sm text-red-600" role="alert"></p>
        <div class="mt-6 flex justify-end gap-3">
            <button type="button" onclick="closeWhatsAppShare()" class="rounded-md px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-[#2A2A28]">{{ __('Cancel') }}</button>
            <button id="whatsapp-share-submit" type="button" onclick="sendWhatsAppShare()" class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">{{ __('Share via WhatsApp') }}</button>
        </div>
    </div>
</div>

<script>
    let whatsappShareData = null;

    async function openWhatsAppShare(url) {
        const modal = document.getElementById('whatsapp-share-modal');
        const error = document.getElementById('whatsapp-share-error');
        error.classList.add('hidden');

        try {
            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('{{ __('Unable to prepare this report for sharing.') }}');

            whatsappShareData = await response.json();
            document.getElementById('whatsapp-share-client').textContent = whatsappShareData.client_name;
            document.getElementById('whatsapp-share-phone').value = whatsappShareData.phone || '';
            document.getElementById('whatsapp-share-message').value = whatsappShareData.message;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        } catch (exception) {
            alert(exception.message || '{{ __('Unable to prepare this report for sharing.') }}');
        }
    }

    function closeWhatsAppShare() {
        const modal = document.getElementById('whatsapp-share-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        whatsappShareData = null;
    }

    function normalizedWhatsAppPhone(value) {
        const digits = value.replace(/\D/g, '');
        return digits.length >= 8 && digits.length <= 15 ? digits : null;
    }

    async function sendWhatsAppShare() {
        if (!whatsappShareData) return;

        const submit = document.getElementById('whatsapp-share-submit');
        const error = document.getElementById('whatsapp-share-error');
        const phoneInput = document.getElementById('whatsapp-share-phone');
        const message = document.getElementById('whatsapp-share-message').value;
        const phone = phoneInput.value.trim() ? normalizedWhatsAppPhone(phoneInput.value) : null;

        if (phoneInput.value.trim() && !phone) {
            error.textContent = '{{ __('Enter a valid phone number including country code.') }}';
            error.classList.remove('hidden');
            phoneInput.focus();
            return;
        }

        try {
            submit.disabled = true;
            const response = await fetch(whatsappShareData.image_url, { headers: { 'Accept': 'image/*' } });
            if (!response.ok) throw new Error('{{ __('Failed to fetch report image.') }}');

            const blob = await response.blob();
            const file = new File([blob], whatsappShareData.file_name, { type: blob.type || 'image/jpeg' });
            const sharePayload = { files: [file], text: message, title: whatsappShareData.client_name };

            if (navigator.share && (!navigator.canShare || navigator.canShare(sharePayload))) {
                await navigator.share(sharePayload);
                closeWhatsAppShare();
                return;
            }

            if (!navigator.clipboard || !window.ClipboardItem) {
                throw new Error('{{ __('Your browser cannot share or copy this report image.') }}');
            }

            await writeReportClipboard(blob, message);
            const whatsappUrl = phone
                ? `https://wa.me/${phone}?text=${encodeURIComponent(message)}`
                : `https://wa.me/?text=${encodeURIComponent(message)}`;
            window.open(whatsappUrl, '_blank', 'noopener');
            closeWhatsAppShare();
        } catch (exception) {
            if (exception && exception.name === 'AbortError') return;
            error.textContent = exception.message || '{{ __('Unable to share this report.') }}';
            error.classList.remove('hidden');
        } finally {
            submit.disabled = false;
        }
    }
</script>
