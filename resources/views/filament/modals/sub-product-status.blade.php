<div class="space-y-4">
    <p class="text-sm text-gray-600">This is the sub-product configuration that the API returns.</p>
    
    <div class="bg-gray-50 rounded-lg p-4">
        <pre class="text-xs overflow-x-auto">{{ json_encode($status, JSON_PRETTY_PRINT) }}</pre>
    </div>
    
    <div class="border-t pt-4">
        <h3 class="font-medium mb-2">API Endpoints</h3>
        <div class="space-y-2 text-sm">
            <div>
                <code class="bg-gray-100 px-2 py-1 rounded">GET /api/sub-products</code>
                <span class="text-gray-600 ml-2">Returns the status of every sub-product.</span>
            </div>
            <div>
                <code class="bg-gray-100 px-2 py-1 rounded">GET /api/sub-products/{sub-product}</code>
                <span class="text-gray-600 ml-2">Returns the status of one sub-product.</span>
            </div>
        </div>
    </div>
</div>