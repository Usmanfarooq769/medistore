import Swal from 'sweetalert2';

export { Swal };

export const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2500,
    timerProgressBar: true,
});

export const notify = (title, icon = 'success') => toast.fire({ icon, title });

export const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

export async function confirm({ title = 'Are you sure?', text = "You won't be able to revert this!", confirmText = 'Yes, do it!', icon = 'warning', color = '#dc3545' } = {}) {
    const r = await Swal.fire({ title, text, icon, showCancelButton: true, confirmButtonColor: color, confirmButtonText: confirmText, reverseButtons: true });
    return r.isConfirmed;
}

export function errorAlert(data = {}, status = 0) {
    if (status === 422 && data.errors) {
        const list = Object.values(data.errors).flat().map((m) => `<li>${esc(m)}</li>`).join('');
        return Swal.fire({ icon: 'error', title: 'Please check', html: `<ul class="text-start mb-0">${list}</ul>` });
    }
    if (status === 403) return Swal.fire({ icon: 'warning', title: 'Not allowed', text: data.message || 'You do not have permission.' });
    if (status === 404) return Swal.fire({ icon: 'info', title: 'Not found', text: data.message || 'Record not found.' });
    return Swal.fire({ icon: 'error', title: 'Oops...', text: data.message || 'Something went wrong. Please try again.' });
}
