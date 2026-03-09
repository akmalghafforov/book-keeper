<script>
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
                try {
                    // Try to copy both image and text
                    console.log('Attempting to copy image and text...');
                    
                    // Note: Some browsers/apps only pick the first or last item if multiple are provided.
                    // WhatsApp Web often ignores text if an image is present in the same ClipboardItem.
                    // We try to provide them in a way that maximizes compatibility.
                    
                    let data;
                    try {
                        // Attempt 1: Separate items (some clipboard managers like this)
                        data = [
                            new ClipboardItem({ [blob.type]: blob }),
                            new ClipboardItem({ 'text/plain': new Blob([whatsappText], { type: 'text/plain' }) })
                        ];
                        await navigator.clipboard.write(data);
                        console.log('Separate items copy succeeded');
                    } catch (e) {
                        console.warn('Separate items copy failed, trying combined item...', e);
                        // Attempt 2: Combined item (Standard way)
                        data = [
                            new ClipboardItem({ 
                                [blob.type]: blob,
                                'text/plain': new Blob([whatsappText], { type: 'text/plain' })
                            }),
                        ];
                        await navigator.clipboard.write(data);
                        console.log('Combined item copy succeeded');
                    }
                } catch (combinedError) {
                    console.warn('Failed to copy combined data, trying image only...', combinedError);
                    // Fallback: Try image only
                    const data = [new ClipboardItem({ 
                        [blob.type]: blob
                    })];
                    await navigator.clipboard.write(data);
                    
                    // If image only succeeded, notify user text wasn't copied
                    console.log('Image only copy succeeded');
                }
                
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
