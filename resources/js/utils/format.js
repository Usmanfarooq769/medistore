let currency = 'Rs.';

export const setCurrency = (c) => { currency = c || 'Rs.'; };
export const getCurrency = () => currency;

export const money = (n) => `${currency} ${Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
export const num = (n) => Number(n || 0).toLocaleString();

export const date = (d) => {
    if (!d) return '—';
    const x = new Date(String(d).length <= 10 ? `${d}T00:00:00` : String(d).replace(' ', 'T'));
    return isNaN(x) ? d : x.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

export const dateTime = (d) => {
    if (!d) return '—';
    const x = new Date(String(d).replace(' ', 'T'));
    return isNaN(x) ? d : x.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

export const today = () => {
    const d = new Date();
    return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
};

export const shiftDate = (iso, days) => {
    const d = new Date(`${iso}T00:00:00`);
    d.setDate(d.getDate() + days);
    return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
};

export const daysLeft = (d) => (d ? Math.ceil((new Date(`${d}T00:00:00`) - new Date(new Date().toDateString())) / 86400000) : null);

export const statusClass = (s) => ({ paid: 'bg-success', partial: 'bg-warning text-dark', unpaid: 'bg-danger' }[s] || 'bg-secondary');

export const conditionClass = (c) => ({ good: 'bg-success', damaged: 'bg-danger', expired: 'bg-dark' }[c] || 'bg-secondary');

export const debounce = (fn, ms = 300) => {
    let t;
    return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
};

/** Tell layout / other pages that stock changed (refresh alert bell) */
export const stockChanged = () => window.dispatchEvent(new Event('stock-changed'));
