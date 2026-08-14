document.addEventListener('DOMContentLoaded', function() {
    const garageButtons = document.querySelectorAll('.garage-btn');
    
    // Cargar el estado guardado del localStorage
    garageButtons.forEach(button => {
        const id = button.getAttribute('data-id');
        const savedState = localStorage.getItem(`vehicle-${id}`);
        
        if (savedState === 'true') {
            button.classList.add('active');
            button.textContent = 'Sí';
        }
    });
    
    // Agregar evento click a cada botón
    garageButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            if (this.classList.contains('active')) {
                this.classList.remove('active');
                this.textContent = 'No';
                localStorage.setItem(`vehicle-${id}`, 'false');
            } else {
                this.classList.add('active');
                this.textContent = 'Sí';
                localStorage.setItem(`vehicle-${id}`, 'true');
            }
        });
    });
});