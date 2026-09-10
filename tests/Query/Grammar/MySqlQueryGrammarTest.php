<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query\Grammar;

use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Query\Clause\Where;
use Dirthara\Database\Query\Join\JoinType;
use Dirthara\Database\Query\Clause\OrderBy;
use Dirthara\Database\Query\Clause\WhereIn;
use Dirthara\Database\Query\Clause\RawWhere;
use Dirthara\Database\Query\Join\JoinClause;
use Dirthara\Database\Query\Clause\WhereNull;
use Dirthara\Database\Query\Clause\NestedWhere;
use Dirthara\Database\Query\Clause\WhereColumn;
use Dirthara\Database\Query\Clause\WhereExists;
use Dirthara\Database\Query\Expression\Aliased;
use Dirthara\Database\Query\Clause\WhereBetween;
use Dirthara\Database\Query\Queries\DeleteQuery;
use Dirthara\Database\Query\Queries\InsertQuery;
use Dirthara\Database\Query\Queries\UpdateQuery;
use Dirthara\Database\Query\Clause\OrderDirection;
use Dirthara\Database\Query\Expression\Identifier;
use Dirthara\Database\Query\Expression\RawExpression;
use Dirthara\Database\Query\Operator\BooleanOperator;
use Dirthara\Database\Query\Grammar\MySqlQueryGrammar;
use Dirthara\Database\Query\Operator\ComparisonOperator;
use Dirthara\Database\Tests\Query\Grammar\Doubles\UnsupportedExpression;

final class MySqlQueryGrammarTest extends GrammarTestCase
{
    private MySqlQueryGrammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grammar = new MySqlQueryGrammar();
    }

    #[Test]
    public function it_selects_every_column(): void
    {
        $query = $this->grammar->compileSelect($this->select());

        self::assertSame('SELECT * FROM `users`', $query->sql);
        self::assertSame([], $query->bindings);
    }

    #[Test]
    public function it_quotes_a_qualified_table(): void
    {
        self::assertSame(
            'SELECT * FROM `app`.`users`',
            $this->grammar->compileSelect($this->select(table: 'app.users'))->sql,
        );
    }

    #[Test]
    public function it_aliases_a_table(): void
    {
        self::assertSame(
            'SELECT `u`.`name` FROM `users` AS `u`',
            $this->grammar->compileSelect($this->select(
                table: new Aliased(new Identifier('users'), 'u'),
                columns: $this->columns('u.name'),
            ))->sql,
        );
    }

    #[Test]
    public function it_aliases_a_column(): void
    {
        self::assertSame(
            'SELECT `users`.`name` AS `n` FROM `users`',
            $this->grammar->compileSelect($this->select(columns: [new Aliased(
                new Identifier('users.name'),
                'n',
            )]))->sql,
        );
    }

    #[Test]
    public function it_aliases_a_raw_expression_and_keeps_its_bindings(): void
    {
        $query = $this->grammar->compileSelect($this->select(columns: [new Aliased(
            new RawExpression('COALESCE(name, ?)', ['unknown']),
            'name',
        )], wheres: [new Where(new Identifier('active'), ComparisonOperator::Equal, 1, BooleanOperator::And)]));

        self::assertSame('SELECT COALESCE(name, ?) AS `name` FROM `users` WHERE `active` = ?', $query->sql);
        self::assertSame(['unknown', 1], $query->bindings);
    }

    #[Test]
    public function it_aliases_a_joined_table(): void
    {
        self::assertSame(
            'SELECT * FROM `users` INNER JOIN `posts` AS `p` ON `users`.`id` = `p`.`user_id`',
            $this->grammar->compileSelect($this->select(joins: [new JoinClause(
                new Aliased(new Identifier('posts'), 'p'),
                new Identifier('users.id'),
                ComparisonOperator::Equal,
                new Identifier('p.user_id'),
                JoinType::Inner,
            )]))->sql,
        );
    }

    #[Test]
    public function it_emits_a_raw_table_as_written(): void
    {
        self::assertSame(
            'SELECT `u`.`name` FROM users AS u',
            $this->grammar->compileSelect($this->select(
                table: new RawExpression('users AS u'),
                columns: $this->columns('u.name'),
            ))->sql,
        );
    }

    #[Test]
    public function it_joins_a_raw_table(): void
    {
        self::assertSame(
            'SELECT * FROM `users` INNER JOIN posts AS p ON `users`.`id` = `p`.`user_id`',
            $this->grammar->compileSelect($this->select(joins: [new JoinClause(
                new RawExpression('posts AS p'),
                new Identifier('users.id'),
                ComparisonOperator::Equal,
                new Identifier('p.user_id'),
                JoinType::Inner,
            )]))->sql,
        );
    }

    #[Test]
    public function it_binds_a_raw_table_before_the_conditions(): void
    {
        $query = $this->grammar->compileSelect($this->select(
            table: new RawExpression('history(?) AS h', ['2026-01-01']),
            columns: $this->columns('h.name'),
            wheres: [new Where(new Identifier('h.active'), ComparisonOperator::Equal, 1, BooleanOperator::And)],
        ));

        self::assertSame('SELECT `h`.`name` FROM history(?) AS h WHERE `h`.`active` = ?', $query->sql);
        self::assertSame(['2026-01-01', 1], $query->bindings);
    }

    #[Test]
    public function it_quotes_identifiers_with_backticks(): void
    {
        self::assertSame(
            'SELECT `id`, `name` FROM `users`',
            $this->grammar->compileSelect($this->select(columns: $this->columns('id', 'name')))->sql,
        );
    }

    #[Test]
    public function it_quotes_each_segment_of_a_qualified_column(): void
    {
        self::assertSame(
            'SELECT `users`.`id` FROM `users`',
            $this->grammar->compileSelect($this->select(columns: $this->columns('users.id')))->sql,
        );
    }

    #[Test]
    public function it_keeps_a_wildcard_unquoted(): void
    {
        self::assertSame(
            'SELECT `users`.* FROM `users`',
            $this->grammar->compileSelect($this->select(columns: $this->columns('users.*')))->sql,
        );
    }

    #[Test]
    public function it_escapes_a_backtick_in_a_column_name(): void
    {
        $query = $this->grammar->compileInsert(new InsertQuery(new Identifier('users'), [['we`ird' => 1]]));

        self::assertSame('INSERT INTO `users` (`we``ird`) VALUES (?)', $query->sql);
    }

    #[Test]
    public function it_emits_a_raw_expression_as_written(): void
    {
        self::assertSame(
            'SELECT COUNT(*) AS total FROM `users`',
            $this->grammar->compileSelect($this->select(columns: [new RawExpression('COUNT(*) AS total')]))->sql,
        );
    }

    #[Test]
    public function it_escapes_a_backtick_in_a_selected_identifier(): void
    {
        self::assertSame(
            'SELECT `we``ird` FROM `users`',
            $this->grammar->compileSelect($this->select(columns: $this->columns('we`ird')))->sql,
        );
    }

    #[Test]
    public function it_binds_the_values_of_a_raw_expression(): void
    {
        $query = $this->grammar->compileSelect($this->select(columns: [new RawExpression('IF(age > ?, ?, ?) AS bracket', [
            40,
            'senior',
            'junior',
        ])]));

        self::assertSame('SELECT IF(age > ?, ?, ?) AS bracket FROM `users`', $query->sql);
        self::assertSame([40, 'senior', 'junior'], $query->bindings);
    }

    #[Test]
    public function it_binds_a_raw_column_before_the_conditions(): void
    {
        $query = $this->grammar->compileSelect($this->select(columns: [new RawExpression('COALESCE(name, ?) AS name', [
            'unknown',
        ])], wheres: [new Where(new Identifier('active'), ComparisonOperator::Equal, 1, BooleanOperator::And)]));

        self::assertSame('SELECT COALESCE(name, ?) AS name FROM `users` WHERE `active` = ?', $query->sql);
        self::assertSame(['unknown', 1], $query->bindings);
    }

    #[Test]
    public function it_binds_every_clause_in_the_order_the_query_emits_it(): void
    {
        $query = $this->grammar->compileSelect($this->select(
            columns: [new RawExpression('COALESCE(name, ?) AS name', ['a'])],
            joins: [new JoinClause(
                new Identifier('posts'),
                new RawExpression('IFNULL(users.id, ?)', ['b']),
                ComparisonOperator::Equal,
                new Identifier('posts.user_id'),
                JoinType::Inner,
            )],
            wheres: [new Where(new Identifier('active'), ComparisonOperator::Equal, 'c', BooleanOperator::And)],
            groups: [new RawExpression('DATE(created_at + ?)', ['d'])],
            havings: [new Where(new Identifier('total'), ComparisonOperator::GreaterThan, 'e', BooleanOperator::And)],
            orders: [new OrderBy(new RawExpression('FIELD(name, ?)', ['f']), OrderDirection::Ascending)],
        ));

        self::assertSame(
            'SELECT COALESCE(name, ?) AS name FROM `users`'
            . ' INNER JOIN `posts` ON IFNULL(users.id, ?) = `posts`.`user_id`'
            . ' WHERE `active` = ? GROUP BY DATE(created_at + ?) HAVING `total` > ?'
            . ' ORDER BY FIELD(name, ?) ASC',
            $query->sql,
        );
        self::assertSame(['a', 'b', 'c', 'd', 'e', 'f'], $query->bindings);
    }

    #[Test]
    public function it_binds_a_raw_column_before_a_membership_test(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new WhereIn(
                new RawExpression('COALESCE(role, ?)', ['guest']),
                ['admin', 'owner'],
                false,
                BooleanOperator::And,
            ),
        ]));

        self::assertSame('SELECT * FROM `users` WHERE COALESCE(role, ?) IN (?, ?)', $query->sql);
        self::assertSame(['guest', 'admin', 'owner'], $query->bindings);
    }

    #[Test]
    public function it_binds_a_raw_column_before_a_range(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new WhereBetween(new RawExpression('age + ?', [1]), 18, 65, false, BooleanOperator::And),
        ]));

        self::assertSame('SELECT * FROM `users` WHERE age + ? BETWEEN ? AND ?', $query->sql);
        self::assertSame([1, 18, 65], $query->bindings);
    }

    #[Test]
    public function it_counts_a_raw_expression(): void
    {
        $query = $this->grammar->compileCount(
            $this->select(wheres: [
                new Where(new Identifier('active'), ComparisonOperator::Equal, 1, BooleanOperator::And),
            ]),
            new RawExpression('DISTINCT NULLIF(role, ?)', ['guest']),
        );

        self::assertSame(
            'SELECT COUNT(DISTINCT NULLIF(role, ?)) AS `aggregate` FROM `users` WHERE `active` = ?',
            $query->sql,
        );
        self::assertSame(['guest', 1], $query->bindings);
    }

    #[Test]
    public function it_rejects_an_expression_it_does_not_know(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('Unsupported expression [%s].', UnsupportedExpression::class));

        $this->grammar->compileSelect($this->select(columns: [new UnsupportedExpression()]));
    }

    #[Test]
    public function it_compares_a_column_to_a_binding(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new Where(new Identifier('name'), ComparisonOperator::Equal, 'Ada', BooleanOperator::And),
        ]));

        self::assertSame('SELECT * FROM `users` WHERE `name` = ?', $query->sql);
        self::assertSame(['Ada'], $query->bindings);
    }

    #[Test]
    public function it_joins_conditions_with_their_own_boolean(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new Where(new Identifier('name'), ComparisonOperator::Equal, 'Ada', BooleanOperator::And),
            new Where(new Identifier('active'), ComparisonOperator::Equal, 1, BooleanOperator::Or),
            new Where(new Identifier('age'), ComparisonOperator::GreaterThan, 18, BooleanOperator::And),
        ]));

        self::assertSame('SELECT * FROM `users` WHERE `name` = ? OR `active` = ? AND `age` > ?', $query->sql);
        self::assertSame(['Ada', 1, 18], $query->bindings);
    }

    #[Test]
    public function it_wraps_a_raw_condition_in_parentheses(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new RawWhere('LOWER(name) = ?', ['ada'], BooleanOperator::And),
        ]));

        self::assertSame('SELECT * FROM `users` WHERE (LOWER(name) = ?)', $query->sql);
        self::assertSame(['ada'], $query->bindings);
    }

    #[Test]
    public function it_keeps_a_raw_condition_from_reassociating(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new Where(new Identifier('active'), ComparisonOperator::Equal, 1, BooleanOperator::And),
            new RawWhere('a = ? OR b = ?', [2, 3], BooleanOperator::And),
        ]));

        self::assertSame('SELECT * FROM `users` WHERE `active` = ? AND (a = ? OR b = ?)', $query->sql);
        self::assertSame([1, 2, 3], $query->bindings);
    }

    #[Test]
    public function it_joins_a_raw_condition_with_or(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new Where(new Identifier('active'), ComparisonOperator::Equal, 1, BooleanOperator::And),
            new RawWhere('deleted_at IS NULL', [], BooleanOperator::Or),
        ]));

        self::assertSame('SELECT * FROM `users` WHERE `active` = ? OR (deleted_at IS NULL)', $query->sql);
    }

    #[Test]
    public function it_filters_groups_with_a_raw_condition(): void
    {
        $query = $this->grammar->compileSelect($this->select(
            columns: $this->columns('role'),
            groups: $this->columns('role'),
            havings: [new RawWhere('COUNT(*) > ?', [1], BooleanOperator::And)],
        ));

        self::assertSame('SELECT `role` FROM `users` GROUP BY `role` HAVING (COUNT(*) > ?)', $query->sql);
        self::assertSame([1], $query->bindings);
    }

    #[Test]
    public function it_tests_a_group_for_null(): void
    {
        $query = $this->grammar->compileSelect($this->select(
            columns: $this->columns('role'),
            groups: $this->columns('role'),
            havings: [new WhereNull(new RawExpression('MAX(age)'), true, BooleanOperator::And)],
        ));

        self::assertSame('SELECT `role` FROM `users` GROUP BY `role` HAVING MAX(age) IS NOT NULL', $query->sql);
    }

    #[Test]
    public function it_binds_a_raw_condition_in_clause_order(): void
    {
        $query = $this->grammar->compileSelect($this->select(
            columns: [new RawExpression('COALESCE(name, ?) AS name', ['a'])],
            wheres: [new RawWhere('active = ?', ['b'], BooleanOperator::And)],
            groups: $this->columns('role'),
            havings: [new RawWhere('COUNT(*) > ?', ['c'], BooleanOperator::And)],
            orders: [new OrderBy(new RawExpression('FIELD(name, ?)', ['d']), OrderDirection::Ascending)],
        ));

        self::assertSame(['a', 'b', 'c', 'd'], $query->bindings);
    }

    #[Test]
    public function it_tests_for_null(): void
    {
        self::assertSame(
            'SELECT * FROM `users` WHERE `deleted_at` IS NULL',
            $this->grammar->compileSelect($this->select(wheres: [
                new WhereNull(new Identifier('deleted_at'), false, BooleanOperator::And),
            ]))->sql,
        );
    }

    #[Test]
    public function it_tests_for_a_value(): void
    {
        self::assertSame(
            'SELECT * FROM `users` WHERE `deleted_at` IS NOT NULL',
            $this->grammar->compileSelect($this->select(wheres: [
                new WhereNull(new Identifier('deleted_at'), true, BooleanOperator::And),
            ]))->sql,
        );
    }

    #[Test]
    public function it_tests_for_membership(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new WhereIn(new Identifier('id'), [1, 2, 3], false, BooleanOperator::And),
        ]));

        self::assertSame('SELECT * FROM `users` WHERE `id` IN (?, ?, ?)', $query->sql);
        self::assertSame([1, 2, 3], $query->bindings);
    }

    #[Test]
    public function it_tests_for_exclusion(): void
    {
        self::assertSame(
            'SELECT * FROM `users` WHERE `id` NOT IN (?)',
            $this->grammar->compileSelect($this->select(wheres: [
                new WhereIn(new Identifier('id'), [1], true, BooleanOperator::And),
            ]))->sql,
        );
    }

    #[Test]
    public function it_never_matches_an_empty_membership_test(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new WhereIn(new Identifier('id'), [], false, BooleanOperator::And),
        ]));

        self::assertSame('SELECT * FROM `users` WHERE 1 = 0', $query->sql);
        self::assertSame([], $query->bindings);
    }

    #[Test]
    public function it_always_matches_an_empty_exclusion(): void
    {
        self::assertSame(
            'SELECT * FROM `users` WHERE 1 = 1',
            $this->grammar->compileSelect($this->select(wheres: [
                new WhereIn(new Identifier('id'), [], true, BooleanOperator::And),
            ]))->sql,
        );
    }

    #[Test]
    public function it_tests_a_range(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new WhereBetween(new Identifier('age'), 18, 65, false, BooleanOperator::And),
        ]));

        self::assertSame('SELECT * FROM `users` WHERE `age` BETWEEN ? AND ?', $query->sql);
        self::assertSame([18, 65], $query->bindings);
    }

    #[Test]
    public function it_tests_outside_a_range(): void
    {
        self::assertSame(
            'SELECT * FROM `users` WHERE `age` NOT BETWEEN ? AND ?',
            $this->grammar->compileSelect($this->select(wheres: [
                new WhereBetween(new Identifier('age'), 18, 65, true, BooleanOperator::And),
            ]))->sql,
        );
    }

    #[Test]
    public function it_joins_a_range_with_or(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new Where(new Identifier('name'), ComparisonOperator::Equal, 'Ada', BooleanOperator::And),
            new WhereBetween(new Identifier('age'), 18, 65, true, BooleanOperator::Or),
        ]));

        self::assertSame('SELECT * FROM `users` WHERE `name` = ? OR `age` NOT BETWEEN ? AND ?', $query->sql);
        self::assertSame(['Ada', 18, 65], $query->bindings);
    }

    #[Test]
    public function it_joins_a_subquery_with_or(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new Where(new Identifier('name'), ComparisonOperator::Equal, 'Ada', BooleanOperator::And),
            new WhereExists($this->select(table: 'posts'), true, BooleanOperator::Or),
        ]));

        self::assertSame('SELECT * FROM `users` WHERE `name` = ? OR NOT EXISTS (SELECT * FROM `posts`)', $query->sql);
    }

    #[Test]
    public function it_compares_two_columns(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new WhereColumn(
                new Identifier('created_at'),
                ComparisonOperator::LessThan,
                new Identifier('updated_at'),
                BooleanOperator::And,
            ),
        ]));

        self::assertSame('SELECT * FROM `users` WHERE `created_at` < `updated_at`', $query->sql);
        self::assertSame([], $query->bindings);
    }

    #[Test]
    public function it_groups_nested_conditions(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new Where(new Identifier('active'), ComparisonOperator::Equal, 1, BooleanOperator::And),
            new NestedWhere([
                new Where(new Identifier('name'), ComparisonOperator::Equal, 'Ada', BooleanOperator::And),
                new Where(new Identifier('name'), ComparisonOperator::Equal, 'Grace', BooleanOperator::Or),
            ], BooleanOperator::And),
        ]));

        self::assertSame('SELECT * FROM `users` WHERE `active` = ? AND (`name` = ? OR `name` = ?)', $query->sql);
        self::assertSame([1, 'Ada', 'Grace'], $query->bindings);
    }

    #[Test]
    public function it_tests_for_a_matching_subquery(): void
    {
        $subquery = $this->select(table: new Identifier('posts'), columns: $this->columns('id'), wheres: [new Where(
            new Identifier('posts.views'),
            ComparisonOperator::GreaterThan,
            10,
            BooleanOperator::And,
        )]);

        $query = $this->grammar->compileSelect($this->select(wheres: [
            new Where(new Identifier('active'), ComparisonOperator::Equal, 1, BooleanOperator::And),
            new WhereExists($subquery, false, BooleanOperator::And),
        ]));

        self::assertSame(
            'SELECT * FROM `users` WHERE `active` = ? AND EXISTS (SELECT `id` FROM `posts` WHERE `posts`.`views` > ?)',
            $query->sql,
        );
        self::assertSame([1, 10], $query->bindings);
    }

    #[Test]
    public function it_tests_for_a_missing_subquery(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new WhereExists($this->select(table: new Identifier('posts')), true, BooleanOperator::And),
        ]));

        self::assertSame('SELECT * FROM `users` WHERE NOT EXISTS (SELECT * FROM `posts`)', $query->sql);
    }

    #[Test]
    public function it_joins_a_table(): void
    {
        self::assertSame(
            'SELECT * FROM `users` INNER JOIN `posts` ON `users`.`id` = `posts`.`user_id`',
            $this->grammar->compileSelect($this->select(joins: [
                new JoinClause(
                    new Identifier('posts'),
                    new Identifier('users.id'),
                    ComparisonOperator::Equal,
                    new Identifier('posts.user_id'),
                    JoinType::Inner,
                ),
            ]))->sql,
        );
    }

    #[Test]
    public function it_joins_several_tables_in_order(): void
    {
        self::assertSame(
            'SELECT * FROM `users` LEFT JOIN `posts` ON `users`.`id` = `posts`.`user_id`'
            . ' RIGHT JOIN `teams` ON `users`.`team_id` = `teams`.`id`',
            $this->grammar->compileSelect($this->select(joins: [
                new JoinClause(
                    new Identifier('posts'),
                    new Identifier('users.id'),
                    ComparisonOperator::Equal,
                    new Identifier('posts.user_id'),
                    JoinType::Left,
                ),
                new JoinClause(
                    new Identifier('teams'),
                    new Identifier('users.team_id'),
                    ComparisonOperator::Equal,
                    new Identifier('teams.id'),
                    JoinType::Right,
                ),
            ]))->sql,
        );
    }

    #[Test]
    public function it_rejects_a_full_join(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('MySQL does not support a full join.');

        $this->grammar->compileSelect($this->select(joins: [
            new JoinClause(
                new Identifier('posts'),
                new Identifier('users.id'),
                ComparisonOperator::Equal,
                new Identifier('posts.user_id'),
                JoinType::Full,
            ),
        ]));
    }

    #[Test]
    public function it_groups_and_filters_groups(): void
    {
        $query = $this->grammar->compileSelect($this->select(
            columns: $this->columns('role'),
            groups: $this->columns('role'),
            havings: [new Where(new Identifier('role'), ComparisonOperator::NotEqual, 'guest', BooleanOperator::And)],
        ));

        self::assertSame('SELECT `role` FROM `users` GROUP BY `role` HAVING `role` != ?', $query->sql);
        self::assertSame(['guest'], $query->bindings);
    }

    #[Test]
    public function it_orders_rows(): void
    {
        self::assertSame(
            'SELECT * FROM `users` ORDER BY `name` ASC, `created_at` DESC',
            $this->grammar->compileSelect($this->select(orders: [
                new OrderBy(new Identifier('name'), OrderDirection::Ascending),
                new OrderBy(new Identifier('created_at'), OrderDirection::Descending),
            ]))->sql,
        );
    }

    #[Test]
    public function it_limits_rows(): void
    {
        self::assertSame(
            'SELECT * FROM `users` LIMIT 10',
            $this->grammar->compileSelect($this->select(limit: 10))->sql,
        );
    }

    #[Test]
    public function it_limits_and_offsets_rows(): void
    {
        self::assertSame(
            'SELECT * FROM `users` LIMIT 10 OFFSET 20',
            $this->grammar->compileSelect($this->select(limit: 10, offset: 20))->sql,
        );
    }

    #[Test]
    public function it_offsets_rows_without_a_limit(): void
    {
        self::assertSame(
            'SELECT * FROM `users` LIMIT 18446744073709551615 OFFSET 20',
            $this->grammar->compileSelect($this->select(offset: 20))->sql,
        );
    }

    #[Test]
    public function it_orders_the_clauses_of_a_full_query(): void
    {
        $query = $this->grammar->compileSelect($this->select(
            columns: $this->columns('users.name'),
            joins: [
                new JoinClause(
                    new Identifier('posts'),
                    new Identifier('users.id'),
                    ComparisonOperator::Equal,
                    new Identifier('posts.user_id'),
                    JoinType::Inner,
                ),
            ],
            wheres: [new Where(new Identifier('users.active'), ComparisonOperator::Equal, 1, BooleanOperator::And)],
            groups: $this->columns('users.name'),
            havings: [new Where(new Identifier('total'), ComparisonOperator::GreaterThan, 2, BooleanOperator::And)],
            orders: [new OrderBy(new Identifier('users.name'), OrderDirection::Ascending)],
            limit: 5,
            offset: 10,
        ));

        self::assertSame(
            'SELECT `users`.`name` FROM `users` INNER JOIN `posts` ON `users`.`id` = `posts`.`user_id`'
            . ' WHERE `users`.`active` = ? GROUP BY `users`.`name` HAVING `total` > ?'
            . ' ORDER BY `users`.`name` ASC LIMIT 5 OFFSET 10',
            $query->sql,
        );
        self::assertSame([1, 2], $query->bindings);
    }

    #[Test]
    public function it_compiles_an_existence_check(): void
    {
        $query = $this->grammar->compileExists($this->select(wheres: [new Where(
            new Identifier('active'),
            ComparisonOperator::Equal,
            1,
            BooleanOperator::And,
        )]));

        self::assertSame('SELECT EXISTS(SELECT * FROM `users` WHERE `active` = ?) AS `exists`', $query->sql);
        self::assertSame([1], $query->bindings);
    }

    #[Test]
    public function it_counts_rows(): void
    {
        $query = $this->grammar->compileCount($this->select(), new Identifier('*'));

        self::assertSame('SELECT COUNT(*) AS `aggregate` FROM `users`', $query->sql);
        self::assertSame([], $query->bindings);
    }

    #[Test]
    public function it_counts_a_column(): void
    {
        self::assertSame(
            'SELECT COUNT(`name`) AS `aggregate` FROM `users`',
            $this->grammar->compileCount($this->select(), new Identifier('name'))->sql,
        );
    }

    #[Test]
    public function it_drops_ordering_and_paging_from_a_count(): void
    {
        $query = $this->grammar->compileCount(
            $this->select(
                wheres: [new Where(new Identifier('active'), ComparisonOperator::Equal, 1, BooleanOperator::And)],
                orders: [new OrderBy(new Identifier('name'), OrderDirection::Ascending)],
                limit: 10,
                offset: 5,
            ),
            new Identifier('*'),
        );

        self::assertSame('SELECT COUNT(*) AS `aggregate` FROM `users` WHERE `active` = ?', $query->sql);
        self::assertSame([1], $query->bindings);
    }

    #[Test]
    public function it_counts_the_rows_of_a_grouped_query(): void
    {
        $query = $this->grammar->compileCount(
            $this->select(
                columns: $this->columns('role'),
                wheres: [new Where(new Identifier('active'), ComparisonOperator::Equal, 1, BooleanOperator::And)],
                groups: $this->columns('role'),
            ),
            new Identifier('*'),
        );

        self::assertSame(
            'SELECT COUNT(*) AS `aggregate` FROM'
            . ' (SELECT `role` FROM `users` WHERE `active` = ? GROUP BY `role`) AS `aggregate`',
            $query->sql,
        );
        self::assertSame([1], $query->bindings);
    }

    #[Test]
    public function it_counts_the_groups_of_a_query_without_chosen_columns(): void
    {
        $query = $this->grammar->compileCount(
            $this->select(groups: $this->columns('role', 'team')),
            new Identifier('*'),
        );

        self::assertSame(
            'SELECT COUNT(*) AS `aggregate` FROM'
            . ' (SELECT `role`, `team` FROM `users` GROUP BY `role`, `team`) AS `aggregate`',
            $query->sql,
        );
    }

    #[Test]
    public function it_inserts_a_row(): void
    {
        $query = $this->grammar->compileInsert(new InsertQuery(new Identifier('users'), [[
            'name' => 'Ada',
            'active' => 1,
        ]]));

        self::assertSame('INSERT INTO `users` (`name`, `active`) VALUES (?, ?)', $query->sql);
        self::assertSame(['Ada', 1], $query->bindings);
    }

    #[Test]
    public function it_inserts_several_rows(): void
    {
        $query = $this->grammar->compileInsert(new InsertQuery(new Identifier('users'), [
            ['name' => 'Ada', 'active' => 1],
            ['name' => 'Grace', 'active' => 0],
        ]));

        self::assertSame('INSERT INTO `users` (`name`, `active`) VALUES (?, ?), (?, ?)', $query->sql);
        self::assertSame(['Ada', 1, 'Grace', 0], $query->bindings);
    }

    #[Test]
    public function it_inserts_a_null_value(): void
    {
        $query = $this->grammar->compileInsert(new InsertQuery(new Identifier('users'), [['name' => null]]));

        self::assertSame('INSERT INTO `users` (`name`) VALUES (?)', $query->sql);
        self::assertSame([null], $query->bindings);
    }

    #[Test]
    public function it_updates_rows(): void
    {
        $query = $this->grammar->compileUpdate(new UpdateQuery(
            table: new Identifier('users'),
            values: ['name' => 'Ada', 'active' => 1],
            wheres: [new Where(new Identifier('id'), ComparisonOperator::Equal, 7, BooleanOperator::And)],
            orders: [],
            limit: null,
        ));

        self::assertSame('UPDATE `users` SET `name` = ?, `active` = ? WHERE `id` = ?', $query->sql);
        self::assertSame(['Ada', 1, 7], $query->bindings);
    }

    #[Test]
    public function it_updates_every_row(): void
    {
        $query = $this->grammar->compileUpdate(new UpdateQuery(
            table: new Identifier('users'),
            values: ['active' => 0],
            wheres: [],
            orders: [],
            limit: null,
        ));

        self::assertSame('UPDATE `users` SET `active` = ?', $query->sql);
        self::assertSame([0], $query->bindings);
    }

    #[Test]
    public function it_limits_an_update(): void
    {
        $query = $this->grammar->compileUpdate(new UpdateQuery(
            table: new Identifier('users'),
            values: ['active' => 0],
            wheres: [new Where(new Identifier('active'), ComparisonOperator::Equal, 1, BooleanOperator::And)],
            orders: [],
            limit: 1,
        ));

        self::assertSame('UPDATE `users` SET `active` = ? WHERE `active` = ? LIMIT 1', $query->sql);
        self::assertSame([0, 1], $query->bindings);
    }

    #[Test]
    public function it_orders_an_update(): void
    {
        $query = $this->grammar->compileUpdate(new UpdateQuery(
            table: new Identifier('users'),
            values: ['active' => 0],
            wheres: [new Where(new Identifier('active'), ComparisonOperator::Equal, 1, BooleanOperator::And)],
            orders: [new OrderBy(new Identifier('created_at'), OrderDirection::Ascending)],
            limit: 1,
        ));

        self::assertSame(
            'UPDATE `users` SET `active` = ? WHERE `active` = ? ORDER BY `created_at` ASC LIMIT 1',
            $query->sql,
        );
        self::assertSame([0, 1], $query->bindings);
    }

    #[Test]
    public function it_orders_a_delete(): void
    {
        $query = $this->grammar->compileDelete(new DeleteQuery(
            table: new Identifier('users'),
            wheres: [],
            orders: [
                new OrderBy(new Identifier('created_at'), OrderDirection::Ascending),
                new OrderBy(new Identifier('id'), OrderDirection::Descending),
            ],
            limit: 5,
        ));

        self::assertSame('DELETE FROM `users` ORDER BY `created_at` ASC, `id` DESC LIMIT 5', $query->sql);
    }

    #[Test]
    public function it_binds_a_raw_order_of_a_delete_last(): void
    {
        $query = $this->grammar->compileDelete(new DeleteQuery(
            table: new Identifier('users'),
            wheres: [new Where(new Identifier('active'), ComparisonOperator::Equal, 1, BooleanOperator::And)],
            orders: [new OrderBy(new RawExpression('FIELD(name, ?)', ['Ada']), OrderDirection::Ascending)],
            limit: 1,
        ));

        self::assertSame('DELETE FROM `users` WHERE `active` = ? ORDER BY FIELD(name, ?) ASC LIMIT 1', $query->sql);
        self::assertSame([1, 'Ada'], $query->bindings);
    }

    #[Test]
    public function it_deletes_rows(): void
    {
        $query = $this->grammar->compileDelete(new DeleteQuery(
            table: new Identifier('users'),
            wheres: [new Where(new Identifier('id'), ComparisonOperator::Equal, 7, BooleanOperator::And)],
            orders: [],
            limit: null,
        ));

        self::assertSame('DELETE FROM `users` WHERE `id` = ?', $query->sql);
        self::assertSame([7], $query->bindings);
    }

    #[Test]
    public function it_deletes_every_row(): void
    {
        self::assertSame(
            'DELETE FROM `users`',
            $this->grammar->compileDelete(new DeleteQuery(
                table: new Identifier('users'),
                wheres: [],
                orders: [],
                limit: null,
            ))->sql,
        );
    }

    #[Test]
    public function it_limits_a_delete(): void
    {
        self::assertSame(
            'DELETE FROM `users` LIMIT 5',
            $this->grammar->compileDelete(new DeleteQuery(
                table: new Identifier('users'),
                wheres: [],
                orders: [],
                limit: 5,
            ))->sql,
        );
    }
}
