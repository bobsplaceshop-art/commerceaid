<section class="sf-section" style="padding: 5rem 2rem; background: var(--sf-surface-1); text-align: center;">
    <div style="max-width: 600px; margin: 0 auto;">
        <h2 style="font-family: var(--sf-font-display); font-size: 2rem; margin-bottom: 1rem;"><?= e($config['headline'] ?? '') ?></h2>
        <p style="color: var(--sf-muted); margin-bottom: 2rem;"><?= e($config['subheadline'] ?? '') ?></p>
        <form style="display: flex; gap: 0.5rem; justify-content: center;">
            <input type="email" placeholder="Your email address" style="padding: 1rem; border: 1px solid var(--sf-border); border-radius: 4px; width: 300px; max-width: 100%;">
            <button type="button" class="sf-btn sf-btn-primary" style="padding: 1rem 2rem;"><?= e($config['button_text'] ?? 'Subscribe') ?></button>
        </form>
    </div>
</section>
