/* DistribuTrack — Main JS */

// Sidebar toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
}

// Init DataTables on any table with .dt-table class
$(document).ready(function () {
    if ($.fn.DataTable) {
        $('.dt-table').DataTable({
            pageLength: 15,
            lengthMenu: [10, 15, 25, 50, 100],
            language: {
                search: '',
                searchPlaceholder: 'Search...',
                lengthMenu: 'Show _MENU_',
                info: 'Showing _START_–_END_ of _TOTAL_',
                emptyTable: 'No records yet.',
                paginate: {
                    previous: '<i class="bi bi-chevron-left"></i>',
                    next: '<i class="bi bi-chevron-right"></i>'
                }
            },
            dom: '<"d-flex align-items-center justify-content-between mb-3"lf>rt<"d-flex align-items-center justify-content-between mt-3"ip>',
            order: [[0, 'desc']]
        });
    }

    // Auto-dismiss alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(el => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            if (bsAlert) bsAlert.close();
        });
    }, 4000);
});

// Confirm delete
function confirmDelete(msg) {
    return confirm(msg || 'Are you sure you want to delete this record? This action cannot be undone.');
}

// Export to PDF
function exportToPDF(tableId, title, filename) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape' });

    doc.setFontSize(16);
    doc.setFont('helvetica', 'bold');
    doc.text(title, 14, 18);
    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    doc.text('Generated: ' + new Date().toLocaleString(), 14, 25);

    const table = document.getElementById(tableId);
    const headers = [];
    const rows = [];

    table.querySelectorAll('thead th').forEach(th => {
        if (!th.classList.contains('no-export')) headers.push(th.innerText.trim());
    });

    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach((td, i) => {
            if (!tr.querySelectorAll('td')[i]?.classList.contains('no-export')) {
                row.push(td.innerText.trim());
            }
        });
        if (row.length) rows.push(row);
    });

    doc.autoTable({
        head: [headers],
        body: rows,
        startY: 30,
        styles: { fontSize: 9, cellPadding: 3 },
        headStyles: { fillColor: [245, 166, 35], textColor: [0, 0, 0], fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [245, 245, 245] }
    });

    doc.save(filename || 'report.pdf');
}

// Export to Excel
function exportToExcel(tableId, title, filename) {
    const table = document.getElementById(tableId);
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(table, { raw: false });
    XLSX.utils.book_append_sheet(wb, ws, title || 'Report');
    XLSX.writeFile(wb, filename || 'report.xlsx');
}

// Print
function printArea(elementId) {
    const content = document.getElementById(elementId).innerHTML;
    const win = window.open('', '_blank');
    win.document.write(`
        <html><head>
        <title>Print</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
        <style>
            body { padding: 20px; font-family: sans-serif; }
            .no-print { display: none !important; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 8px; font-size: 12px; }
            th { background: #f0f0f0; font-weight: bold; }
        </style>
        </head><body>` + content + `</body></html>`
    );
    win.document.close();
    win.print();
}

// Format currency
function formatCurrency(amount) {
    return 'Rs. ' + parseFloat(amount || 0).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Edit modal helper
function openEditModal(modalId, data) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    Object.entries(data).forEach(([key, val]) => {
        const el = modal.querySelector(`[name="${key}"]`);
        if (el) el.value = val;
    });
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}
