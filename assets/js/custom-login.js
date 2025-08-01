
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('loginForm');
    const loginButton = document.getElementById('loginButton');
    
    // Add loading state to button on form submit
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            form.classList.add('was-validated');
        } else {
            // Show loading state
            loginButton.classList.add('loading');
            loginButton.disabled = true;
            document.querySelector('.button-text').textContent = 'Signing in...';
            
            // In a real app, you would handle the form submission here
            // For demo purposes, we'll simulate a network request
            setTimeout(() => {
                loginButton.classList.remove('loading');
                loginButton.disabled = false;
                document.querySelector('.button-text').textContent = 'Sign In';
            }, 2000);
        }
    });
    
    // Add input focus effects
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
        // Add focus class when input is focused
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        // Remove focus class when input loses focus
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
        
        // Real-time validation
        input.addEventListener('input', function() {
            if (this.checkValidity()) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
            }
        });
    });
    
    // Toggle password visibility
    const passwordInput = document.getElementById('password');
    const togglePassword = document.createElement('span');
    togglePassword.className = 'input-group-text bg-transparent';
    togglePassword.innerHTML = '<i class="far fa-eye"></i>';
    togglePassword.style.cursor = 'pointer';
    togglePassword.style.position = 'absolute';
    togglePassword.style.right = '10px';
    togglePassword.style.top = '50%';
    togglePassword.style.transform = 'translateY(-50%)';
    passwordInput.parentNode.style.position = 'relative';
    passwordInput.parentNode.appendChild(togglePassword);
    
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.innerHTML = type === 'password' ? '<i class="far fa-eye"></i>' : '<i class="far fa-eye-slash"></i>';
    });
});