document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const domainForm = document.getElementById('domainForm');
    const domainInput = document.getElementById('domainInput');
    const loadingElement = document.getElementById('loading');
    const errorElement = document.getElementById('error');
    const errorMessage = document.getElementById('errorMessage');
    const resultsElement = document.getElementById('results');
    const lastUpdatedElement = document.getElementById('lastUpdated');
    
    // Tab functionality
    const tabs = document.querySelectorAll('[data-tab]');
    const tabContents = document.querySelectorAll('.tab-content');
    
    // Current domain data
    let currentDomainData = null;

    // Event Listeners
    domainForm.addEventListener('submit', handleDomainSubmit);
    
    // Tab switching
    tabs.forEach(tab => {
        tab.addEventListener('click', () => switchTab(tab.dataset.tab));
    });

    // Functions
    async function handleDomainSubmit(e) {
        e.preventDefault();
        
        const domain = domainInput.value.trim();
        if (!domain) return;
        
        // Show loading state
        showLoading();
        
        try {
            // Fetch domain information
            const data = await fetchDomainInfo(domain);
            currentDomainData = data;
            
            // Update UI with domain data
            updateDomainOverview(data);
            updatePerformanceTab(data);
            updateSecurityTab(data);
            updateDnsTab(data);
            updateWhoisTab(data);
            
            // Show results
            showResults();
            
            // Update last checked time
            updateLastChecked();
            
        } catch (error) {
            showError(error.message || 'Failed to fetch domain information');
        } finally {
            hideLoading();
        }
    }
    
    async function fetchDomainInfo(domain) {
        try {
            const response = await fetch(`/domain-info.php?domain=${encodeURIComponent(domain)}`);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to fetch domain information');
            }
            
            return data.data;
        } catch (error) {
            console.error('Error fetching domain info:', error);
            throw new Error('Failed to connect to the server. Please try again later.');
        }
    }
    
    function updateDomainOverview(data) {
        // Update domain name and status
        document.getElementById('domainName').textContent = data.domain;
        
        const statusElement = document.getElementById('domainStatus');
        statusElement.textContent = data.uptime.status === 'up' ? 'Online' : 'Offline';
        statusElement.className = `px-3 py-1 rounded-full text-sm font-medium ${
            data.uptime.status === 'up' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
        }`;
        
        // Update domain metadata
        const metaElement = document.getElementById('domainMeta');
        metaElement.innerHTML = `
            <div class="flex flex-wrap gap-4 mt-2">
                <span class="flex items-center">
                    <span class="status-indicator ${data.uptime.status === 'up' ? 'status-up' : 'status-down'}"></span>
                    Uptime: ${data.uptime.uptime_24h}
                </span>
                <span>Response: ${data.speed.load_time_ms}ms</span>
                <span>SSL: ${data.ssl.valid ? 'Valid' : 'Invalid'}</span>
            </div>
        `;
        
        // Update performance metrics in overview
        const performanceElement = document.getElementById('performanceMetrics');
        performanceElement.innerHTML = `
            <div class="metric-card">
                <div class="metric-value">${data.speed.load_time_ms}ms</div>
                <div class="metric-label">Load Time</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">${data.traffic.visits_24h.toLocaleString()}</div>
                <div class="metric-label">Visits (24h)</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">${data.uptime.uptime_24h}</div>
                <div class="metric-label">Uptime (24h)</div>
            </div>
        `;
        
        // Update security metrics in overview
        const securityElement = document.getElementById('securityMetrics');
        securityElement.innerHTML = `
            <div class="metric-card">
                <div class="flex items-center">
                    <span class="text-${data.ssl.valid ? 'green' : 'red'}-500 mr-2">
                        <i class="fas fa-${data.ssl.valid ? 'lock' : 'unlock'}"></i>
                    </span>
                    <div>
                        <div class="font-medium">SSL ${data.ssl.valid ? 'Valid' : 'Invalid'}</div>
                        <div class="text-xs text-gray-500">Expires in ${data.ssl.days_remaining} days</div>
                    </div>
                </div>
            </div>
            <div class="metric-card">
                <div class="font-medium">Issuer</div>
                <div class="text-sm text-gray-600 truncate">${data.ssl.issuer || 'Unknown'}</div>
            </div>
        `;
        
        // Update DNS metrics in overview
        const dnsElement = document.getElementById('dnsMetrics');
        const hasDnsRecords = Object.values(data.dns).some(records => records.length > 0);
        
        if (hasDnsRecords) {
            dnsElement.innerHTML = Object.entries(data.dns)
                .filter(([_, records]) => records.length > 0)
                .map(([type, records]) => `
                    <div class="metric-card">
                        <div class="font-medium uppercase">${type}</div>
                        <div class="text-sm text-gray-600">${records.length} records</div>
                    </div>
                `)
                .join('');
        } else {
            dnsElement.innerHTML = `
                <div class="text-center py-4 text-gray-500">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    No DNS records found
                </div>
            `;
        }
    }
    
    function updatePerformanceTab(data) {
        // Speed metrics
        const speedElement = document.getElementById('speedMetrics');
        speedElement.innerHTML = `
            <div class="metric-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-600">Page Load Time</span>
                    <span class="font-medium">${data.speed.load_time_ms}ms</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-bar-fill bg-${getSpeedColor(data.speed.load_time_ms)}" 
                         style="width: ${Math.min(100, data.speed.load_time_ms / 2)}%">
                    </div>
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    ${getSpeedDescription(data.speed.load_time_ms)}
                </div>
            </div>
            
            <div class="metric-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-600">Page Size</span>
                    <span class="font-medium">${(data.speed.page_size_kb || 0).toFixed(2)} KB</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-bar-fill bg-blue-500" 
                         style="width: ${Math.min(100, (data.speed.page_size_kb || 0) / 2)}%">
                    </div>
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    ${getSizeDescription(data.speed.page_size_kb || 0)}
                </div>
            </div>
            
            <div class="metric-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-600">Uptime (24h)</span>
                    <span class="font-medium">${data.uptime.uptime_24h}</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-bar-fill bg-green-500" 
                         style="width: ${parseFloat(data.uptime.uptime_24h)}%">
                    </div>
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    Last checked: ${new Date().toLocaleTimeString()}
                </div>
            </div>
        `;
        
        // Traffic metrics
        const trafficElement = document.getElementById('trafficMetrics');
        trafficElement.innerHTML = `
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">${data.traffic.visits_24h.toLocaleString()}</div>
                    <div class="text-sm text-gray-600">Visits (24h)</div>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">${data.traffic.pageviews_24h.toLocaleString()}</div>
                    <div class="text-sm text-gray-600">Pageviews (24h)</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">${data.traffic.avg_session_duration}</div>
                    <div class="text-sm text-gray-600">Avg. Session</div>
                </div>
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-yellow-600">${data.traffic.bounce_rate}</div>
                    <div class="text-sm text-gray-600">Bounce Rate</div>
                </div>
            </div>
        `;
        
        // Traffic sources
        const sourcesElement = document.getElementById('trafficSources');
        sourcesElement.innerHTML = `
            <div class="space-y-3">
                ${Object.entries(data.traffic.traffic_sources || {})
                    .map(([source, percentage]) => `
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="capitalize">${source}</span>
                                <span class="font-medium">${percentage}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-${getSourceColor(source)}-500 h-2 rounded-full" 
                                     style="width: ${parseInt(percentage)}">
                                </div>
                            </div>
                        </div>
                    `).join('')}
            </div>
            
            <div class="mt-6">
                <h4 class="font-medium text-gray-700 mb-3">Top Pages</h4>
                <div class="space-y-2">
                    ${(data.traffic.top_pages || []).map(page => `
                        <div class="flex justify-between text-sm p-2 bg-gray-50 rounded">
                            <span class="truncate">${page.url}</span>
                            <span class="ml-2 font-medium">${page.visits.toLocaleString()}</span>
                        </div>
                    `).join('')}
                    }
                </div>
            </div>
        `;
    }
    
    function updateSecurityTab(data) {
        // SSL Info
        const sslElement = document.getElementById('sslInfo');
        sslElement.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-700 mb-2">Certificate Details</h4>
                    <div class="space-y-3">
                        <div>
                            <div class="text-sm text-gray-500">Status</div>
                            <div class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                    data.ssl.valid ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                }">
                                    ${data.ssl.valid ? 'Valid' : 'Invalid'}
                                </span>
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Issuer</div>
                            <div class="font-medium">${data.ssl.issuer || 'Unknown'}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Subject</div>
                            <div class="font-medium">${data.ssl.subject || 'Unknown'}</div>
                        </div>
                    </div>
                </div>
                <div>
                    <h4 class="font-medium text-gray-700 mb-2">Validity Period</h4>
                    <div class="space-y-3">
                        <div>
                            <div class="text-sm text-gray-500">Issued On</div>
                            <div>${formatDate(data.ssl.valid_from)}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Expires On</div>
                            <div class="${data.ssl.days_remaining < 30 ? 'text-red-600 font-medium' : ''}">
                                ${formatDate(data.ssl.valid_to)} 
                                (${data.ssl.days_remaining} days remaining)
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Key Size</div>
                            <div>${data.ssl.key_size || 'Unknown'} bits</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-6">
                <h4 class="font-medium text-gray-700 mb-2">Certificate Chain</h4>
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-green-100 flex items-center justify-center text-green-600">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900">${data.ssl.subject || 'Your connection'}</div>
                            <div class="text-sm text-gray-500">
                                Encrypted connection (${data.ssl.signature_algorithm || 'TLS 1.2+'})
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    function updateDnsTab(data) {
        // DNS Records
        const dnsElement = document.getElementById('dnsRecords');
        const nameServersElement = document.getElementById('nameServers');
        
        // Filter out empty DNS record types
        const dnsRecords = Object.entries(data.dns || {})
            .filter(([_, records]) => Array.isArray(records) && records.length > 0);
        
        if (dnsRecords.length > 0) {
            dnsElement.innerHTML = `
                <div class="space-y-4">
                    ${dnsRecords.map(([type, records]) => `
                        <div>
                            <h5 class="text-sm font-medium text-gray-700 mb-1">${type.toUpperCase()} Records</h5>
                            <div class="bg-gray-50 rounded-md p-3">
                                ${records.map(record => {
                                    if (type === 'mx') {
                                        return `
                                            <div class="text-sm font-mono py-1 border-b border-gray-100 last:border-0">
                                                <div class="text-gray-900">${record.priority} ${record.target}</div>
                                                <div class="text-xs text-gray-500">TTL: ${record.ttl || 'N/A'}</div>
                                            </div>
                                        `;
                                    } else if (type === 'txt') {
                                        return `
                                            <div class="text-sm font-mono py-1 border-b border-gray-100 last:border-0">
                                                <div class="text-gray-900 break-all">${record.txt || 'N/A'}</div>
                                                <div class="text-xs text-gray-500">TTL: ${record.ttl || 'N/A'}</div>
                                            </div>
                                        `;
                                    } else {
                                        return `
                                            <div class="text-sm font-mono py-1 border-b border-gray-100 last:border-0">
                                                <div class="text-gray-900">${record.ip || record.target || record.txt || 'N/A'}</div>
                                                <div class="text-xs text-gray-500">TTL: ${record.ttl || 'N/A'}</div>
                                            </div>
                                        `;
                                    }
                                }).join('')}
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        } else {
            dnsElement.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                    <p>No DNS records found</p>
                </div>
            `;
        }
        
        // Name servers
        if (data.whois?.name_servers?.length > 0) {
            nameServersElement.innerHTML = `
                <div class="space-y-2">
                    ${data.whois.name_servers.map(ns => `
                        <div class="flex items-center p-3 bg-gray-50 rounded-md">
                            <i class="fas fa-server text-gray-400 mr-3"></i>
                            <span class="font-mono text-sm">${ns}</span>
                        </div>
                    `).join('')}
                </div>
            `;
        } else {
            nameServersElement.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                    <p>No nameservers found</p>
                </div>
            `;
        }
    }
    
    function updateWhoisTab(data) {
        const whoisElement = document.getElementById('whoisInfo');
        
        if (!data.whois || Object.keys(data.whois).length === 0) {
            whoisElement.innerHTML = `
                <div class="text-center py-12 text-gray-500">
                    <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                    <p>No WHOIS information available</p>
                </div>
            `;
            return;
        }
        
        whoisElement.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Domain Information</h3>
                    <div class="space-y-4">
                        ${renderWhoisField('Domain Name', data.domain)}
                        ${renderWhoisField('Registrar', data.whois.registrar)}
                        ${renderWhoisField('Registration Date', formatDate(data.whois.created_date))}
                        ${renderWhoisField('Expiration Date', formatDate(data.whois.expiry_date))}
                        ${renderWhoisField('Last Updated', formatDate(data.whois.updated_date))}
                        ${renderWhoisField('Status', data.whois.status)}
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Nameservers</h3>
                    <div class="space-y-2">
                        ${data.whois.name_servers && data.whois.name_servers.length > 0 
                            ? data.whois.name_servers.map(ns => `
                                <div class="flex items-center p-3 bg-gray-50 rounded-md">
                                    <i class="fas fa-server text-gray-400 mr-3"></i>
                                    <span class="font-mono text-sm">${ns}</span>
                                </div>
                            `).join('')
                            : '<p class="text-gray-500">No nameservers found</p>'
                        }
                    </div>
                </div>
            </div>
            
            <div class="mt-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Raw WHOIS Data</h3>
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <pre class="text-xs text-gray-700 overflow-x-auto">${JSON.stringify(data.whois, null, 2)}</pre>
                </div>
            </div>
        `;
    }
    
    function renderWhoisField(label, value) {
        if (!value) return '';
        return `
            <div>
                <div class="text-sm font-medium text-gray-500">${label}</div>
                <div class="mt-1 text-gray-900">${value}</div>
            </div>
        `;
    }
    
    function switchTab(tabId) {
        // Update active tab
        tabs.forEach(tab => {
            if (tab.dataset.tab === tabId) {
                tab.classList.add('border-indigo-500', 'text-indigo-600');
                tab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            } else {
                tab.classList.remove('border-indigo-500', 'text-indigo-600');
                tab.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            }
        });
        
        // Show active tab content
        tabContents.forEach(content => {
            if (content.id === `${tabId}-tab`) {
                content.classList.remove('hidden');
                content.classList.add('fade-in');
            } else {
                content.classList.add('hidden');
                content.classList.remove('fade-in');
            }
        });
    }
    
    function showLoading() {
        loadingElement.classList.remove('hidden');
        errorElement.classList.add('hidden');
        resultsElement.classList.add('hidden');
    }
    
    function hideLoading() {
        loadingElement.classList.add('hidden');
    }
    
    function showError(message) {
        errorMessage.textContent = message;
        errorElement.classList.remove('hidden');
    }
    
    function showResults() {
        resultsElement.classList.remove('hidden');
        resultsElement.scrollIntoView({ behavior: 'smooth' });
    }
    
    function updateLastChecked() {
        lastUpdatedElement.textContent = `Last checked: ${new Date().toLocaleString()}`;
    }
    
    // Helper functions
    function getSpeedColor(loadTime) {
        if (loadTime < 500) return 'green';
        if (loadTime < 1500) return 'yellow';
        return 'red';
    }
    
    function getSpeedDescription(loadTime) {
        if (loadTime < 500) return 'Fast';
        if (loadTime < 1500) return 'Average';
        return 'Slow';
    }
    
    function getSizeDescription(sizeKb) {
        if (sizeKb < 100) return 'Small';
        if (sizeKb < 500) return 'Medium';
        if (sizeKb < 2000) return 'Large';
        return 'Very Large';
    }
    
    function getSourceColor(source) {
        const colors = {
            direct: 'blue',
            search: 'green',
            social: 'purple',
            referral: 'pink',
            email: 'indigo',
            organic: 'teal',
            paid: 'orange',
            other: 'gray'
        };
        return colors[source] || 'gray';
    }
    
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return dateString;
        }
    }
    
    // Initialize first tab as active
    if (tabs.length > 0) {
        switchTab(tabs[0].dataset.tab);
    }
});
