# Existing MCP Usage

This project already has MCP servers for Qdrant and Perplexity configured outside this package.

Do not create, overwrite, or assume ownership of the project's MCP configuration.

## Qdrant MCP

Use the existing Qdrant MCP / code index when:

- locating classes, components, modules, event handlers, agents, REST integrations, or CRM custom field usage
- analyzing architecture
- preparing a refactor
- checking whether logic already exists
- answering questions about the local codebase

Before editing code, search the code index for related symbols and concepts.

## Perplexity MCP

Use the existing Perplexity MCP when:

- current or external documentation is needed
- Bitrix24 REST documentation needs verification and official Bitrix24 MCP is not configured
- PHP/library behavior may be version-sensitive
- the answer depends on current documentation

When using Perplexity for Bitrix24, prefer official Bitrix24 documentation and clearly separate documented facts from inference.

## Bitrix24 REST MCP

If an official Bitrix24 REST MCP server is also available, use it before Perplexity for exact REST method details.

If it is not available, do not invent methods. Use Perplexity restricted to official Bitrix24 docs and verify against the local boxed installation when possible.
