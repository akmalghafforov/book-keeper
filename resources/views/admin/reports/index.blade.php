@extends('layouts.admin')

@section('title', __('Generated Reports'))
@section('header_title', __('Reports'))

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Generated Reports') }}</h2>
    </div>

    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative dark:bg-green-900 dark:text-green-100 dark:border-green-800" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-[#3E3E3A]">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                <thead class="bg-gray-50 dark:bg-[#1C1C1A]">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Report Name') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Format') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Created At') }}</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-[#161615] divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                    @forelse ($reports as $report)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                {{ $report->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 uppercase">
                                {{ $report->format }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($report->status === 'pending')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-100">
                                        {{ __('Pending') }}
                                    </span>
                                @elseif($report->status === 'completed')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100">
                                        {{ __('Completed') }}
                                    </span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100" title="{{ $report->error_message }}">
                                        {{ __('Failed') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $report->created_at->format('M d, Y H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                                @if($report->status === 'completed' && $report->file_path)
                                    <a href="{{ Storage::url($report->file_path) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                        {{ __('Download') }}
                                    </a>

                                    <button
                                        onclick="shareOnWhatsApp('{{ $report->name }}', '{{ Storage::url($report->file_path) }}')"
                                        class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 inline-flex items-center"
                                        title="{{ __('Share on WhatsApp') }}"
                                    >
                                        <svg class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.246 2.248 3.484 5.232 3.483 8.413-.003 6.557-5.338 11.892-11.893 11.892-1.997-.001-3.951-.5-5.688-1.448l-6.308 1.654zm6.222-4.103c1.73.93 3.332 1.403 4.933 1.403 5.428 0 9.848-4.419 9.85-9.847.001-2.628-1.02-5.101-2.877-6.958-1.856-1.856-4.33-2.878-6.957-2.878-5.428 0-9.848 4.421-9.85 9.849 0 1.832.503 3.541 1.455 5.04l-.963 3.518 3.409-.893zm11.336-6.623c-.301-.15-1.778-.877-2.054-.976-.275-.099-.476-.15-.676.15-.2.3-.776.976-.951 1.176-.174.2-.35.225-.651.075-.301-.15-1.268-.467-2.414-1.49-.893-.796-1.496-1.78-1.672-2.08-.175-.3-.018-.463.13-.611.135-.133.3-.35.45-.525.15-.175.2-.3.3-.5s.05-.375-.025-.525c-.075-.15-.676-1.628-.926-2.228-.244-.583-.493-.504-.676-.513-.175-.008-.375-.01-.576-.01-.2 0-.525.075-.8 0-.275.3-.8 1.15-.8 2.8 0 1.65 1.2 3.25 1.366 3.475.166.225 2.362 3.606 5.722 5.058.799.345 1.423.551 1.91.706.801.255 1.53.219 2.106.133.642-.095 1.778-.727 2.029-1.428.25-.7.25-1.3.175-1.428-.076-.128-.276-.203-.577-.353z"/>
                                        </svg>
                                        {{ __('Share') }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500 dark:text-gray-400">
                                {{ __('No reports generated yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($reports->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-[#3E3E3A]">
                {{ $reports->links() }}
            </div>
        @endif
    </div>
</div>

<script>
    async function shareOnWhatsApp(reportName, reportUrl) {
        // Sanitize filename for the File object
        const sanitizedName = reportName.replace(/[:\\/*?<>|]/g, '_');
        const text = `{{ __('Report') }}: ${reportName}`;

        // Ensure we use the current origin for fetch to avoid CORS/localhost issues
        const fetchUrl = reportUrl.startsWith('http')
            ? reportUrl.replace(/^https?:\/\/[^\/]+/, window.location.origin)
            : reportUrl;

        // Ensure fallback URL is absolute using current origin
        const absoluteUrl = reportUrl.startsWith('http')
            ? reportUrl.replace(/^https?:\/\/[^\/]+/, window.location.origin)
            : window.location.origin + reportUrl;

        try {
            const response = await fetch(fetchUrl);
            const blob = await response.blob();
            const file = new File([blob], `${sanitizedName}.jpg`, { type: 'image/jpeg' });

            // Check if Web Share API is available and supports file sharing
            // const canShare = navigator.share && navigator.canShare({ files: [file] });

            if (navigator.share) {
                await navigator.share({
                    files: [file],
                    text: text,
                });
            } else {
                // Fallback: Try to copy to clipboard if on Desktop/unsupported browser
                let copied = false;
                if (navigator.clipboard && window.ClipboardItem) {
                    try {
                        await navigator.clipboard.write([
                            new ClipboardItem({ [blob.type]: blob })
                        ]);
                        copied = true;
                    } catch (e) {
                        console.warn('Clipboard write failed, falling back to link sharing only.', e);
                    }
                }

                let shareText = text;
                if (copied) {
                    shareText += `\n\n✅ {{ __('Image copied to clipboard. You can now paste it into WhatsApp.') }}`;
                }
                shareText += `\n\n{{ __('Link') }}: ${absoluteUrl}`;

                const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(shareText)}`;
                window.open(whatsappUrl, '_blank');
            }
        } catch (error) {
            console.error('Error sharing:', error);
            const fallbackText = `${text}\n\n{{ __('Link') }}: ${absoluteUrl}`;
            const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(fallbackText)}`;
            window.open(whatsappUrl, '_blank');
        }
    }
</script>
@endsection
