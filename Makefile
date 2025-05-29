.PHONY: help
help: ## Displays this list of targets with descriptions
	@echo "The following commands are available:\n"
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: docs
docs: ## Generate projects docs (from "Documentation" directory)
	mkdir -p Documentation-GENERATED-temp

	docker run --rm --pull always -v "$(shell pwd)":/project -t ghcr.io/typo3-documentation/render-guides:latest --config=Documentation

.PHONY: docs-fast
docs-fast: ## Generate projects docs (from "Documentation" directory)
	mkdir -p Documentation-GENERATED-temp

	docker run --rm -v "$(shell pwd)":/project -t ghcr.io/typo3-documentation/render-guides:latest --config=Documentation

.PHONY: docs-watch
docs-watch: ## Watch for changes and regenerate docs automatically
	@echo "Watching for changes in Documentation directory..."
	@while inotifywait -r -e modify,create,delete,move Documentation/ 2>/dev/null; do \
    	echo "Changes detected, regenerating documentation..."; \
    	$(MAKE) docs-fast; \
    	echo "Documentation updated at $$(date)"; \
	done

.PHONY: watch-install
watch-install: ## Install inotify-tools for file watching (Ubuntu/Debian)
	sudo apt-get update && sudo apt-get install -y inotify-tools