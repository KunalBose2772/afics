<!-- Top Contact Bar - Professional -->
<div class="top-contact-bar"
    style="background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); padding: 12px 0; border-bottom: 3px solid #dc3545;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 d-none d-md-block">
                <div class="d-flex gap-4">
                    <a href="tel:<?= htmlspecialchars($settings['contact_phone'] ?? '+91 755 883 4483') ?>"
                        class="text-decoration-none d-flex align-items-center"
                        style="color: #ffffff; font-size: 0.9rem; transition: all 0.3s ease;"
                        onmouseover="this.style.color='#dc3545';" onmouseout="this.style.color='#ffffff';">
                        <div class="d-flex align-items-center justify-content-center me-2"
                            style="width: 36px; height: 36px; background: rgba(220, 53, 69, 0.2); border-radius: 50%;">
                            <i class="bi bi-telephone-fill" style="color: #dc3545;"></i>
                        </div>
                        <div>
                            <small style="font-size: 0.7rem; opacity: 0.7; display: block;">On-Call Service 24/7</small>
                            <strong><?= htmlspecialchars($settings['contact_phone'] ?? '+91 755 883 4483') ?></strong>
                        </div>
                    </a>
                    <a href="mailto:<?= htmlspecialchars($settings['contact_email'] ?? 'support@documantraa.in') ?>"
                        class="text-decoration-none d-flex align-items-center"
                        style="color: #ffffff; font-size: 0.9rem; transition: all 0.3s ease;"
                        onmouseover="this.style.color='#dc3545';" onmouseout="this.style.color='#ffffff';">
                        <div class="d-flex align-items-center justify-content-center me-2"
                            style="width: 36px; height: 36px; background: rgba(220, 53, 69, 0.2); border-radius: 50%;">
                            <i class="bi bi-envelope-fill" style="color: #dc3545;"></i>
                        </div>
                        <div>
                            <small style="font-size: 0.7rem; opacity: 0.7; display: block;">Email Address</small>
                            <strong><?= htmlspecialchars($settings['contact_email'] ?? 'support@documantraa.in') ?></strong>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <div class="d-flex justify-content-end align-items-center gap-2">
                    <span style="color: rgba(255,255,255,0.7); font-size: 0.85rem; margin-right: 10px;">Follow
                        Us:</span>
                    <?php if (!empty($settings['social_facebook'])): ?>
                        <a href="<?= htmlspecialchars($settings['social_facebook']) ?>" class="social-icon-top"
                            style="width: 32px; height: 32px; background: rgba(255,255,255,0.1); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #ffffff; transition: all 0.3s ease;"
                            onmouseover="this.style.background='#dc3545'; this.style.transform='translateY(-2px)';"
                            onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.transform='translateY(0)';"><i
                                class="bi bi-facebook"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($settings['social_twitter'])): ?>
                        <a href="<?= htmlspecialchars($settings['social_twitter']) ?>" class="social-icon-top"
                            style="width: 32px; height: 32px; background: rgba(255,255,255,0.1); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #ffffff; transition: all 0.3s ease;"
                            onmouseover="this.style.background='#dc3545'; this.style.transform='translateY(-2px)';"
                            onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.transform='translateY(0)';"><i
                                class="bi bi-twitter"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($settings['social_instagram'])): ?>
                        <a href="<?= htmlspecialchars($settings['social_instagram']) ?>" class="social-icon-top"
                            style="width: 32px; height: 32px; background: rgba(255,255,255,0.1); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #ffffff; transition: all 0.3s ease;"
                            onmouseover="this.style.background='#dc3545'; this.style.transform='translateY(-2px)';"
                            onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.transform='translateY(0)';"><i
                                class="bi bi-instagram"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($settings['social_youtube'])): ?>
                        <a href="<?= htmlspecialchars($settings['social_youtube']) ?>" class="social-icon-top"
                            style="width: 32px; height: 32px; background: rgba(255,255,255,0.1); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #ffffff; transition: all 0.3s ease;"
                            onmouseover="this.style.background='#dc3545'; this.style.transform='translateY(-2px)';"
                            onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.transform='translateY(0)';"><i
                                class="bi bi-youtube"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>