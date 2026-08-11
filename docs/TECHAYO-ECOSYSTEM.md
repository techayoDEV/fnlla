# FNLLA In The TechAyo Ecosystem

FNLLA is TechAyo LTD's open-source application and web infrastructure layer.

It exists to provide a compact, understandable and maintained foundation for server-rendered business software: routing, controllers, views, authentication, authorisation, database work, operational tooling, release workflows and the integrated FNLLA UI runtime.

## Why TechAyo maintains a framework

TechAyo builds products and operational systems as well as customer software. Without a shared foundation, each delivery can become an unrelated collection of dependencies, conventions and maintenance decisions.

FNLLA creates a reusable application contract so common engineering work can be solved deliberately and reused where appropriate.

The goal is not framework novelty for its own sake. The goal is operational ownership:

- an application lifecycle a small team can trace;
- predictable project structure;
- maintained security and operational primitives;
- shared UI/runtime conventions;
- local-first tooling and reproducible release checks;
- explicit upgrade and support boundaries.

## Strategic role

A useful high-level distinction inside the TechAyo ecosystem is:

```text
FNLLA -> application infrastructure
Fionn -> intelligence infrastructure
```

FNLLA provides the reusable software foundation on which business applications can be built. Fionn is a separate TechAyo research and product project focused on persistent intelligence, memory, controlled learning and replaceable model packages.

The two projects do not need to be coupled. Their relationship is architectural and strategic: TechAyo is developing owned foundations for both application delivery and intelligence while keeping individual products responsible for their own integration choices.

## Compounding engineering

The intended operating loop is:

```text
Framework capability
       |
       v
Products and customer systems
       |
       v
Real operational requirements
       |
       v
Reusable improvement, when appropriate
       |
       +--------------------------> FNLLA
```

Not every customer-specific feature belongs upstream. Product branding, business rules, customer data and proprietary workflows remain in their own repositories. Only capabilities that genuinely belong to the shared application foundation should move into FNLLA.

That boundary matters because reuse is valuable only when it does not erase product ownership or turn the framework into an accumulation of unrelated customer code.

## Positioning

A concise description is:

> FNLLA is TechAyo's compact open-source PHP framework for building understandable, maintainable server-rendered business applications with an integrated runtime and operational toolchain.

Within TechAyo, FNLLA is less about competing on framework size and more about providing a dependable application foundation the company can understand, maintain and evolve itself.

For implementation details, supported runtime contracts and current capabilities, the repository `README.md` and versioned documentation remain the source of truth.
