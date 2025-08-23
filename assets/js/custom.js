(function(){
const btn = document.getElementById('backToTop');
if (!btn) return;
const onScroll = () => {
    if (window.scrollY > 200) {
    btn.classList.add('show');
    } else {
    btn.classList.remove('show');
    }
};
window.addEventListener('scroll', onScroll, { passive: true });
btn.addEventListener('click', function(){
    window.scrollTo({ top: 0, behavior: 'smooth' });
});
// Initialize state on load
onScroll();
})();