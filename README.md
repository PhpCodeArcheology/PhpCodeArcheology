# PhpLegacyAnalyzer

**PhpCodeArcheology** analyzes your PHP project and provides metrics about your files, classes, methods and functions. 
The HTML report gives you all the information to the deepest level that you need to evaluate your project.

## Installation

Install PhpCodeArcheology with Composer.

```
composer require PhpCodeArcheology/PhpCodeArcheology --dev
```

## Quick start

Start PhpCodeArcheology in your project root:

```
./vendor/bin/phpcodearcheology
```

Out of the box, PhpCodeArcheology scans your **src** dir and creates the report in *tmp/report*.

To include oder exclude folders, define new php file extensions or other settings, use a [yaml configuration file](php-codearch-config-sample.yaml). Please name it *php-codearch-config.yaml* and put it into your project root.

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