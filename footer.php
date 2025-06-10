            </div>
        </div>
    </div>

    <!-- Scroll to Top Button -->
    <div class="scroll-top">
        <i class="fas fa-arrow-up"></i>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
    <script>
        // Fungsi untuk export ke PDF
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const element = document.querySelector('.main-content');
            
            // Sembunyikan tombol sebelum capture
            const buttons = element.querySelectorAll('.no-print');
            buttons.forEach(btn => btn.style.visibility = 'hidden');
            
            html2canvas(element).then(canvas => {
                buttons.forEach(btn => btn.style.visibility = 'visible');
                
                const imgData = canvas.toDataURL('image/png');
                const pdf = new jsPDF('p', 'mm', 'a4');
                const imgProps = pdf.getImageProperties(imgData);
                const pdfWidth = pdf.internal.pageSize.getWidth();
                const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
                
                pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
                pdf.save('laporan-dfanscoffe.pdf');
            });
        }

        // Fungsi untuk konfirmasi penghapusan
        function confirmDelete() {
            return confirm('Apakah Anda yakin ingin menghapus data ini?');
        }

        // Auto calculate ROP
        function calculateROP() {
            const safetyStock = document.querySelector('input[name="safety_stock"]').value;
            const leadTime = document.querySelector('input[name="lead_time"]').value;
            const ropInput = document.querySelector('input[name="rop"]');
            
            if (safetyStock && leadTime) {
                ropInput.value = parseInt(safetyStock) + parseInt(leadTime);
            }
        }

        // Update stok info
        function updateStokInfo(bahanId) {
            fetch(`get_stok.php?id=${bahanId}`)
                .then(response => response.json())
                .then(data => {
                    const stokInfo = document.getElementById(`stok-info-${bahanId}`);
                    
                    if (stokInfo) {
                        stokInfo.innerHTML = `
                            <div class="alert ${data.stok < data.safety_stock ? 'alert-danger' : 'alert-success'}">
                                Stok: ${data.stok} | Safety Stock: ${data.safety_stock} | ROP: ${data.rop}
                            </div>
                        `;
                    }
                });
        }

        // FEFO info
        function getFEFOInfo(bahanId) {
            fetch(`get_fefo.php?bahan_id=${bahanId}`)
                .then(response => response.json())
                .then(data => {
                    const fefoInfo = document.getElementById(`fefo-info-${bahanId}`);
                    
                    if (fefoInfo) {
                        if (data.success) {
                            fefoInfo.innerHTML = `
                                <div class="alert alert-info">
                                    <strong>FEFO:</strong> Gunakan batch yang expired pada 
                                    ${new Date(data.expired_date).toLocaleDateString()} 
                                    (${data.days_left} hari lagi)
                                </div>
                            `;
                        } else {
                            fefoInfo.innerHTML = `
                                <div class="alert alert-warning">
                                    ${data.message}
                                </div>
                            `;
                        }
                    }
                });
        }
    </script>
</body>
</html>