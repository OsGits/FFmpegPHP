    </div>
    <footer>
        <div class="container">
            <div class="footer-content">
                <p>&copy; <?php echo date('Y'); ?> 视频转码切割工具 | 基于 FFmpeg 实现</p>
            </div>
        </div>
    </footer>
</body>
</html>

<style>
    /* Footer 样式 */
    footer {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        color: rgba(255, 255, 255, 0.9);
        padding: 24px 0;
        margin-top: 40px;
    }

    .footer-content {
        text-align: center;
        font-size: 0.875rem;
    }

    .footer-content p {
        margin: 0;
    }

    @media (max-width: 768px) {
        footer {
            padding: 20px 0;
        }

        .footer-content {
            font-size: 0.8rem;
        }
    }
</style>
