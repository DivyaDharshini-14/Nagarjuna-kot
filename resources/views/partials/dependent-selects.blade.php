<script>
    document.addEventListener('DOMContentLoaded', function () {
        const company = document.querySelector('[data-company-select]');
        const branch = document.querySelector('[data-branch-select]');
        const childSelectors = ['[data-floor-select]', '[data-kitchen-select]', '[data-category-select]', '[data-menu-type-select]', '[data-menu-item-select]'];

        function placeholderOption(select) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = select.dataset.placeholder || 'Select';
            return option;
        }

        async function load(select, params) {
            if (!select || !select.dataset.url) {
                return;
            }

            const url = new URL(select.dataset.url, window.location.origin);
            Object.entries(params).forEach(function ([key, value]) {
                if (value) {
                    url.searchParams.set(key, value);
                }
            });

            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
            select.replaceChildren(placeholderOption(select));

            if (!response.ok) {
                return;
            }

            const items = await response.json();
            items.forEach(function (item) {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.label;
                select.appendChild(option);
            });
        }

        function currentParams() {
            return {
                company_id: company?.value || document.querySelector('[name="company_id"]')?.value || '',
                branch_id: branch?.value || document.querySelector('[name="branch_id"]')?.value || '',
            };
        }

        async function refreshChildren() {
            const params = currentParams();
            for (const selector of childSelectors) {
                const select = document.querySelector(selector);
                if (select) {
                    await load(select, params);
                }
            }
        }

        company?.addEventListener('change', async function () {
            if (branch && !branch.disabled) {
                await load(branch, { company_id: company.value });
            }
            await refreshChildren();
        });

        branch?.addEventListener('change', refreshChildren);
    });
</script>
