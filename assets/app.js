(function () {
    const form = document.getElementById('answer-form');
    const timerValue = document.getElementById('timer-value');
    const timerBox = document.querySelector('[data-timer-box]');
    const timedOutField = document.getElementById('timed-out');

    if (!form || !timerValue || !timerBox || !timedOutField) {
        return;
    }

    let seconds = parseInt(form.dataset.seconds || '0', 10);
    if (Number.isNaN(seconds) || seconds < 0) {
        seconds = 0;
    }

    function paintState(value) {
        timerBox.classList.remove('warning', 'danger');

        if (value <= 5) {
            timerBox.classList.add('danger');
        } else if (value <= 10) {
            timerBox.classList.add('warning');
        }
    }

    function render() {
        timerValue.textContent = String(seconds);
        paintState(seconds);
    }

    function submitTimeout() {
        timedOutField.value = '1';
        form.requestSubmit();
    }

    render();

    const interval = window.setInterval(() => {
        seconds -= 1;
        if (seconds <= 0) {
            seconds = 0;
            render();
            window.clearInterval(interval);
            submitTimeout();
            return;
        }

        render();
    }, 1000);
})();
