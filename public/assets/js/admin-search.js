document.addEventListener('DOMContentLoaded', function() {
    const wrapper = document.querySelector('.ra-search-wrapper');
    if (!wrapper) return;

    const input = wrapper.querySelector('input[name="q"]');
    if (!input) return;

    // Create the dropdown container
    const dropdown = document.createElement('div');
    dropdown.style.cssText = 'position:absolute; top:100%; left:0; right:0; background:#1a2235; border:1px solid #2d3748; border-radius:6px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); z-index:1000; display:none; max-height:400px; overflow-y:auto; margin-top:4px;';
    wrapper.appendChild(dropdown);

    let timeout = null;

    input.addEventListener('input', function() {
        clearTimeout(timeout);
        const q = this.value.trim();

        if (q.length < 2) {
            dropdown.style.display = 'none';
            return;
        }

        timeout = setTimeout(() => {
            fetch(`/commerce/ca-admin/search/live?q=${encodeURIComponent(q)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.total === 0) {
                        dropdown.innerHTML = '<div style="padding:1rem; color:#9ca3af; font-size:0.9rem;">No results found.</div>';
                    } else {
                        let html = '';
                        
                        if (data.products.length > 0) {
                            html += '<div style="padding:0.5rem 1rem; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#9ca3af; border-bottom:1px solid #2d3748;">Products</div>';
                            data.products.forEach(p => {
                                html += `<a href="${p.url}" style="display:flex; justify-content:space-between; padding:0.6rem 1rem; color:#e5e7eb; text-decoration:none; border-bottom:1px solid #2d3748; font-size:0.9rem;" onmouseover="this.style.background='#2d3748'" onmouseout="this.style.background='transparent'">
                                    <div>
                                        <div style="font-weight:600;">${p.name}</div>
                                        <div style="font-size:0.75rem; color:#9ca3af;">SKU: ${p.sku || 'N/A'}</div>
                                    </div>
                                    <div style="color:#d4af37; font-weight:600;">${parseFloat(p.price).toFixed(2)}</div>
                                </a>`;
                            });
                        }

                        if (data.orders.length > 0) {
                            html += '<div style="padding:0.5rem 1rem; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#9ca3af; border-bottom:1px solid #2d3748;">Orders</div>';
                            data.orders.forEach(o => {
                                html += `<a href="${o.url}" style="display:flex; justify-content:space-between; padding:0.6rem 1rem; color:#e5e7eb; text-decoration:none; border-bottom:1px solid #2d3748; font-size:0.9rem;" onmouseover="this.style.background='#2d3748'" onmouseout="this.style.background='transparent'">
                                    <div>
                                        <div style="font-weight:600;">#${o.number}</div>
                                        <div style="font-size:0.75rem; color:#9ca3af;">${o.customer}</div>
                                    </div>
                                    <div style="font-size:0.75rem; text-transform:uppercase;">${o.status}</div>
                                </a>`;
                            });
                        }
                        
                        dropdown.innerHTML = html;
                    }
                    dropdown.style.display = 'block';
                });
        }, 300); // 300ms debounce
    });

    // Hide dropdown on click outside
    document.addEventListener('click', function(e) {
        if (!wrapper.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
});
