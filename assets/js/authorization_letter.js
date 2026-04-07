// Download as PDF functionality with better error handling
const downloadBtn = document.getElementById('downloadBtn');

if (downloadBtn) {
    downloadBtn.addEventListener('click', async function () {
        const btn = this;
        const originalText = btn.innerHTML;

        // Show loading state (spinner icon)
        btn.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spinner">
                <circle cx="12" cy="12" r="10"></circle>
            </svg>
        `;
        btn.disabled = true;

        // Add spinner animation
        if (!document.getElementById('spinner-style')) {
            const style = document.createElement('style');
            style.id = 'spinner-style';
            style.textContent = `
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
                .spinner {
                    animation: spin 1s linear infinite;
                }
            `;
            document.head.appendChild(style);
        }

        try {
            const element = document.getElementById('letterContent');

            // Use html2canvas to capture the element
            const canvas = await html2canvas(element, {
                scale: 2,
                useCORS: true,
                allowTaint: false,
                backgroundColor: '#ffffff',
                logging: false,
                width: 793, // A4 width pixels
                height: 1122
            });

            // Create PDF using jsPDF
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4',
                compress: true
            });

            // Calculate dimensions to fit A4
            const imgWidth = 210;
            const imgHeight = (canvas.height * imgWidth) / canvas.width;

            const imgData = canvas.toDataURL('image/jpeg', 1.0);
            pdf.addImage(imgData, 'JPEG', 0, 0, imgWidth, imgHeight);

            // Save the PDF
            pdf.save('Authorization_Letter_DOCUMANTRAA.pdf');

            // Reset button state
            btn.innerHTML = originalText;
            btn.disabled = false;

            // Show success feedback (Green bg temporarily)
            const origBg = btn.style.backgroundColor;
            btn.style.backgroundColor = '#28a745';
            setTimeout(() => {
                btn.style.backgroundColor = origBg; // Revert
            }, 2000);
        } catch (error) {
            console.error('PDF generation error:', error);
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert('Error generating PDF: ' + error.message);
        }
    });
}

// Ensure images are loaded before logic (helpful for canvas)
window.addEventListener('load', function () {
    const images = document.querySelectorAll('img');
    images.forEach(img => {
        if (!img.complete) {
            img.addEventListener('error', (e) => {
                console.error('Image failed to load:', e.target.src);
            });
        }
    });
});
