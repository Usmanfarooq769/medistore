/** Export the table that is on screen (current page / filters) to Excel, PDF or CSV. */
export function tableToMatrix(root) {
    const table = root.querySelector('table');
    if (!table) return [];
    const heads = [...table.querySelectorAll('thead th')];
    const keep = heads.map((th, i) => ({ i, text: th.innerText.trim() })).filter((h) => h.text && !/^actions?$/i.test(h.text));
    const rows = [...table.querySelectorAll('tbody tr')].filter((tr) => !tr.querySelector('.skeleton, .state-box'));
    const clean = (v) => (v || '').replace(/\s*\n\s*/g, ' · ').replace(/\s+/g, ' ').trim();
    return [keep.map((h) => h.text), ...rows.map((tr) => { const tds = tr.querySelectorAll('td'); return keep.map((h) => clean(tds[h.i]?.innerText)); })];
}

const stamp = () => new Date().toISOString().slice(0, 10);
const fileName = (title) => `${(title || 'export').replace(/[^\w\-]+/g, '_')}_${stamp()}`;

export function exportCsv(matrix, title) {
    const esc = (v) => `"${String(v).replace(/"/g, '""')}"`;
    const csv = '\ufeff' + matrix.map((r) => r.map(esc).join(',')).join('\r\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    a.download = fileName(title) + '.csv';
    a.click();
    URL.revokeObjectURL(a.href);
}

export async function exportXlsx(matrix, title) {
    const XLSX = await import('xlsx');
    const ws = XLSX.utils.aoa_to_sheet(matrix);
    ws['!cols'] = matrix[0].map((_, c) => ({ wch: Math.min(40, Math.max(10, ...matrix.map((r) => String(r[c] ?? '').length)) + 2) }));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, (title || 'Sheet1').slice(0, 30).replace(/[\\/?*[\]:]/g, ' '));
    XLSX.writeFile(wb, fileName(title) + '.xlsx');
}

export async function exportPdf(matrix, title, storeName = '') {
    const [{ jsPDF }, autoTable] = await Promise.all([import('jspdf'), import('jspdf-autotable').then((m) => m.default)]);
    const doc = new jsPDF({ orientation: matrix[0].length > 6 ? 'landscape' : 'portrait', unit: 'pt', format: 'a4' });
    doc.setFontSize(14); doc.text(title || 'Export', 40, 40);
    doc.setFontSize(9); doc.setTextColor(120); doc.text(`${storeName ? storeName + ' · ' : ''}${new Date().toLocaleString()}`, 40, 56);
    autoTable(doc, { head: [matrix[0]], body: matrix.slice(1), startY: 68, styles: { fontSize: 8, cellPadding: 4 }, headStyles: { fillColor: [139, 126, 255] }, alternateRowStyles: { fillColor: [247, 248, 252] }, margin: { left: 40, right: 40 } });
    doc.save(fileName(title) + '.pdf');
}
