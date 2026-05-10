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
