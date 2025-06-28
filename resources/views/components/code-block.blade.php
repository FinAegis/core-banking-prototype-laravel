@props(['language' => 'plaintext', 'title' => null])

@php
    use Highlight\Highlighter;
    
    $code = trim($slot);
    $highlighter = new Highlighter();
    
    try {
        if ($language === 'auto') {
            $highlighted = $highlighter->highlightAuto($code);
            $detectedLanguage = $highlighted->language ?? 'plaintext';
        } else {
            $highlighted = $highlighter->highlight($language, $code);
            $detectedLanguage = $language;
        }
        $highlightedCode = $highlighted->value;
    } catch (\Exception $e) {
        $highlightedCode = htmlspecialchars($code);
        $detectedLanguage = $language;
    }
@endphp

<div class="relative bg-gray-900 rounded-lg overflow-hidden group">
    @if($title)
        <div class="px-4 py-2 bg-gray-800 border-b border-gray-700">
            <span class="text-sm text-gray-400">{{ $title }}</span>
        </div>
    @endif
    
    <div class="relative">
        <button class="absolute top-4 right-4 p-2 text-gray-400 hover:text-white transition opacity-0 group-hover:opacity-100 bg-gray-800 rounded" 
                onclick="copyCode(this)"
                title="Copy code">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
            </svg>
        </button>
        
        <pre class="p-4 overflow-x-auto"><code class="hljs language-{{ $detectedLanguage }}">{!! $highlightedCode !!}</code></pre>
    </div>
</div>

<style>
    /* Highlight.js theme customization */
    .hljs {
        color: #abb2bf;
        background: transparent;
    }
    
    .hljs-comment,
    .hljs-quote {
        color: #5c6370;
        font-style: italic;
    }
    
    .hljs-doctag,
    .hljs-keyword,
    .hljs-formula {
        color: #c678dd;
    }
    
    .hljs-section,
    .hljs-name,
    .hljs-selector-tag,
    .hljs-deletion,
    .hljs-subst {
        color: #e06c75;
    }
    
    .hljs-literal {
        color: #56b6c2;
    }
    
    .hljs-string,
    .hljs-regexp,
    .hljs-addition,
    .hljs-attribute,
    .hljs-meta .hljs-string {
        color: #98c379;
    }
    
    .hljs-attr,
    .hljs-variable,
    .hljs-template-variable,
    .hljs-type,
    .hljs-selector-class,
    .hljs-selector-attr,
    .hljs-selector-pseudo,
    .hljs-number {
        color: #d19a66;
    }
    
    .hljs-symbol,
    .hljs-bullet,
    .hljs-link,
    .hljs-meta,
    .hljs-selector-id,
    .hljs-title {
        color: #61aeee;
    }
    
    .hljs-built_in,
    .hljs-title.class_,
    .hljs-class .hljs-title {
        color: #e6c07b;
    }
    
    .hljs-emphasis {
        font-style: italic;
    }
    
    .hljs-strong {
        font-weight: bold;
    }
    
    .hljs-link {
        text-decoration: underline;
    }
</style>