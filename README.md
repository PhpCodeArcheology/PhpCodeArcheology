# PhpCodeArcheology

**PhpCodeArcheology** analyzes your PHP project, providing detailed metrics on files, classes, methods, and functions. The comprehensive HTML report equips you with deep insights necessary for evaluating your project.

## Installation

Install PhpCodeArcheology using Composer by running the following command in your terminal:

```
composer require PhpCodeArcheology/PhpCodeArcheology --dev
```

## Quick start

To start PhpCodeArcheology, run the following command in your project root:

```
./vendor/bin/phpcodearcheology
```

Out of the box, PhpCodeArcheology scans your **src** dir and creates the report in *tmp/report*.

To customize scanning, such as including or excluding folders, defining new PHP file extensions, or other settings, create a `php-codearch-config.yaml` configuration file in your project root. Refer to this [sample configuration file](php-codearch-config-sample.yaml) for guidance.

## Documentation

More on how to use PhpCodeArcheology and on the used metrics is following here.

## Author

Marcus Kober, [@mrcskbr](https://twitter.com/mrcskbr), [GitHub](https://github.com/marcuskober)

## Docs

- https://www.oreilly.com/library/view/software-architecture-the/9781492086888/ch04.html
- https://www.codinghelmet.com/articles/how-to-measure-module-coupling-and-instability-using-ndepend
- https://stackoverflow.com/questions/1031135/what-is-abstractness-vs-instability-graph
- https://web.archive.org/web/20061211051845/http://www.parlezuml.com/metrics/OO%20Design%20Principles%20%26%20Metrics.pdf
- https://en.wikipedia.org/wiki/Software_package_metrics
- https://kariera.future-processing.pl/blog/object-oriented-metrics-by-robert-martin/
- https://codinghelmet.com/articles/how-to-use-module-coupling-and-instability-metrics-to-guide-refactoring

## Tools

- https://torchlight.dev/ - for syntax highlighting