# ============================================================
# Arpay — Makefile
# ============================================================
# Kullanım:  make <hedef>
# Hepsini:   make check
# ============================================================

.PHONY: help test analyse cs-fix cs-check check demo docker docker-down clean

# Varsayılan hedef
.DEFAULT_GOAL := help

## Yardım — mevcut hedefleri listeler
help:
	@echo ""
	@echo "  Arpay - Kullanılabilir Makefile Hedefleri"
	@echo "  =========================================="
	@echo ""
	@echo "  make test        PHPUnit testlerini çalıştır"
	@echo "  make analyse     PHPStan statik analiz çalıştır"
	@echo "  make cs-fix      PHP-CS-Fixer ile kod stilini düzelt"
	@echo "  make cs-check    Kod stili kontrolü (değişiklik yapmaz)"
	@echo "  make check       Hepsini çalıştır (test + analyse + cs-check)"
	@echo "  make demo        PHP built-in server ile demo başlat"
	@echo "  make docker      Docker Compose ile demo başlat"
	@echo "  make docker-down Docker containerlarını durdur"
	@echo "  make clean       Önbellek dosyalarını temizle"
	@echo ""

## PHPUnit testlerini çalıştır (141 test)
test:
	php vendor/bin/phpunit

## PHPStan statik analiz — Level 8
analyse:
	php vendor/bin/phpstan analyse --no-progress --memory-limit=512M

## PHP-CS-Fixer ile kod stilini otomatik düzelt
cs-fix:
	php vendor/bin/php-cs-fixer fix

## Kod stili kontrolü (salt okunur — CI için)
cs-check:
	php vendor/bin/php-cs-fixer fix --dry-run --diff

## Hepsini çalıştır: test → analyse → cs-check
check: test analyse cs-check
	@echo ""
	@echo "✅ Tüm kontroller başarılı!"

## PHP built-in server ile demo başlat (localhost:8043)
demo:
	@echo "🌐 Demo başlatılıyor: http://localhost:8043"
	php -S localhost:8043 -t demo

## Docker Compose ile demo başlat
docker:
	docker compose up --build -d
	@echo "🐳 Docker demo çalışıyor: http://localhost:8043"

## Docker containerlarını durdur
docker-down:
	docker compose down

## Önbellek ve geçici dosyaları temizle
clean:
	rm -rf .phpunit.cache/ phpstan-cache/
	@echo "🧹 Önbellek temizlendi."
