<?php
/**
 * Integration tests for SMS custom taxonomies.
 */

class Test_SMS_Taxonomies extends SMS_Test_Case {

    /**
     * Test subjects taxonomy registration.
     */
    public function test_subjects_taxonomy_registration() {
        $this->assertTrue(taxonomy_exists('sms_subjects'));
        
        $taxonomy = get_taxonomy('sms_subjects');
        $this->assertNotNull($taxonomy);
        $this->assertEquals('Subjects', $taxonomy->labels->name);
        $this->assertTrue($taxonomy->public);
        $this->assertTrue($taxonomy->show_ui);
        $this->assertFalse($taxonomy->hierarchical);
    }

    /**
     * Test grades taxonomy registration.
     */
    public function test_grades_taxonomy_registration() {
        $this->assertTrue(taxonomy_exists('sms_grades'));
        
        $taxonomy = get_taxonomy('sms_grades');
        $this->assertNotNull($taxonomy);
        $this->assertEquals('Grades', $taxonomy->labels->name);
        $this->assertTrue($taxonomy->hierarchical);
    }

    /**
     * Test academic years taxonomy registration.
     */
    public function test_academic_years_taxonomy_registration() {
        $this->assertTrue(taxonomy_exists('sms_academic_years'));
        
        $taxonomy = get_taxonomy('sms_academic_years');
        $this->assertNotNull($taxonomy);
        $this->assertEquals('Academic Years', $taxonomy->labels->name);
    }

    /**
     * Test terms taxonomy registration.
     */
    public function test_terms_taxonomy_registration() {
        $this->assertTrue(taxonomy_exists('sms_terms'));
        
        $taxonomy = get_taxonomy('sms_terms');
        $this->assertNotNull($taxonomy);
        $this->assertEquals('Terms', $taxonomy->labels->name);
    }

    /**
     * Test creating and managing taxonomy terms.
     */
    public function test_taxonomy_term_creation() {
        // Create subject terms
        $math_term = wp_insert_term('Mathematics', 'sms_subjects', [
            'description' => 'Mathematics subject',
            'slug' => 'mathematics'
        ]);
        
        $this->assertNotWPError($math_term);
        $this->assertIsArray($math_term);
        $this->assertArrayHasKey('term_id', $math_term);
        
        // Verify term exists
        $term = get_term($math_term['term_id'], 'sms_subjects');
        $this->assertEquals('Mathematics', $term->name);
        $this->assertEquals('mathematics', $term->slug);
        
        // Create grade terms with hierarchy
        $primary_term = wp_insert_term('Primary', 'sms_grades', [
            'description' => 'Primary school grades'
        ]);
        
        $grade_5_term = wp_insert_term('Grade 5', 'sms_grades', [
            'description' => 'Grade 5 students',
            'parent' => $primary_term['term_id']
        ]);
        
        $this->assertNotWPError($grade_5_term);
        
        // Verify hierarchy
        $grade_5 = get_term($grade_5_term['term_id'], 'sms_grades');
        $this->assertEquals($primary_term['term_id'], $grade_5->parent);
    }

    /**
     * Test assigning taxonomy terms to posts.
     */
    public function test_taxonomy_term_assignment() {
        // Create a student post
        $student_id = $this->factory->create_student();
        
        // Create grade term
        $grade_term = wp_insert_term('Grade 5', 'sms_grades');
        
        // Assign grade to student
        $result = wp_set_post_terms($student_id, [$grade_term['term_id']], 'sms_grades');
        $this->assertNotWPError($result);
        $this->assertIsArray($result);
        
        // Verify assignment
        $assigned_terms = wp_get_post_terms($student_id, 'sms_grades');
        $this->assertCount(1, $assigned_terms);
        $this->assertEquals('Grade 5', $assigned_terms[0]->name);
    }

    /**
     * Test querying posts by taxonomy terms.
     */
    public function test_taxonomy_queries() {
        // Create grade term
        $grade_5_term = wp_insert_term('Grade 5', 'sms_grades');
        $grade_6_term = wp_insert_term('Grade 6', 'sms_grades');
        
        // Create students and assign grades
        $student_1 = $this->factory->create_student();
        $student_2 = $this->factory->create_student();
        $student_3 = $this->factory->create_student();
        
        wp_set_post_terms($student_1, [$grade_5_term['term_id']], 'sms_grades');
        wp_set_post_terms($student_2, [$grade_5_term['term_id']], 'sms_grades');
        wp_set_post_terms($student_3, [$grade_6_term['term_id']], 'sms_grades');
        
        // Query Grade 5 students
        $query = new WP_Query([
            'post_type' => 'sms_students',
            'tax_query' => [
                [
                    'taxonomy' => 'sms_grades',
                    'field' => 'term_id',
                    'terms' => $grade_5_term['term_id']
                ]
            ]
        ]);
        
        $this->assertEquals(2, $query->found_posts);
        
        // Query Grade 6 students
        $query = new WP_Query([
            'post_type' => 'sms_students',
            'tax_query' => [
                [
                    'taxonomy' => 'sms_grades',
                    'field' => 'term_id',
                    'terms' => $grade_6_term['term_id']
                ]
            ]
        ]);
        
        $this->assertEquals(1, $query->found_posts);
    }

    /**
     * Test multiple taxonomy assignments.
     */
    public function test_multiple_taxonomy_assignments() {
        // Create terms
        $grade_5_term = wp_insert_term('Grade 5', 'sms_grades');
        $math_term = wp_insert_term('Mathematics', 'sms_subjects');
        $english_term = wp_insert_term('English', 'sms_subjects');
        $term_1 = wp_insert_term('Term 1', 'sms_terms');
        
        // Create a class post
        $class_id = $this->factory->create_class();
        
        // Assign multiple taxonomies
        wp_set_post_terms($class_id, [$grade_5_term['term_id']], 'sms_grades');
        wp_set_post_terms($class_id, [$math_term['term_id'], $english_term['term_id']], 'sms_subjects');
        wp_set_post_terms($class_id, [$term_1['term_id']], 'sms_terms');
        
        // Verify assignments
        $grades = wp_get_post_terms($class_id, 'sms_grades');
        $subjects = wp_get_post_terms($class_id, 'sms_subjects');
        $terms = wp_get_post_terms($class_id, 'sms_terms');
        
        $this->assertCount(1, $grades);
        $this->assertCount(2, $subjects);
        $this->assertCount(1, $terms);
        
        $this->assertEquals('Grade 5', $grades[0]->name);
        $this->assertEquals('Mathematics', $subjects[0]->name);
        $this->assertEquals('English', $subjects[1]->name);
        $this->assertEquals('Term 1', $terms[0]->name);
    }

    /**
     * Test complex taxonomy queries.
     */
    public function test_complex_taxonomy_queries() {
        // Create terms
        $grade_5_term = wp_insert_term('Grade 5', 'sms_grades');
        $grade_6_term = wp_insert_term('Grade 6', 'sms_grades');
        $math_term = wp_insert_term('Mathematics', 'sms_subjects');
        $english_term = wp_insert_term('English', 'sms_subjects');
        
        // Create classes with different combinations
        $class_1 = $this->factory->create_class();
        $class_2 = $this->factory->create_class();
        $class_3 = $this->factory->create_class();
        
        // Class 1: Grade 5, Math
        wp_set_post_terms($class_1, [$grade_5_term['term_id']], 'sms_grades');
        wp_set_post_terms($class_1, [$math_term['term_id']], 'sms_subjects');
        
        // Class 2: Grade 5, English
        wp_set_post_terms($class_2, [$grade_5_term['term_id']], 'sms_grades');
        wp_set_post_terms($class_2, [$english_term['term_id']], 'sms_subjects');
        
        // Class 3: Grade 6, Math
        wp_set_post_terms($class_3, [$grade_6_term['term_id']], 'sms_grades');
        wp_set_post_terms($class_3, [$math_term['term_id']], 'sms_subjects');
        
        // Query: Grade 5 AND Math
        $query = new WP_Query([
            'post_type' => 'sms_classes',
            'tax_query' => [
                'relation' => 'AND',
                [
                    'taxonomy' => 'sms_grades',
                    'field' => 'term_id',
                    'terms' => $grade_5_term['term_id']
                ],
                [
                    'taxonomy' => 'sms_subjects',
                    'field' => 'term_id',
                    'terms' => $math_term['term_id']
                ]
            ]
        ]);
        
        $this->assertEquals(1, $query->found_posts);
        
        // Query: Grade 5 OR Math
        $query = new WP_Query([
            'post_type' => 'sms_classes',
            'tax_query' => [
                'relation' => 'OR',
                [
                    'taxonomy' => 'sms_grades',
                    'field' => 'term_id',
                    'terms' => $grade_5_term['term_id']
                ],
                [
                    'taxonomy' => 'sms_subjects',
                    'field' => 'term_id',
                    'terms' => $math_term['term_id']
                ]
            ]
        ]);
        
        $this->assertEquals(3, $query->found_posts);
    }

    /**
     * Test taxonomy term meta data.
     */
    public function test_taxonomy_term_meta() {
        // Create a subject term
        $math_term = wp_insert_term('Mathematics', 'sms_subjects');
        $term_id = $math_term['term_id'];
        
        // Add term meta
        add_term_meta($term_id, 'subject_code', 'MATH101');
        add_term_meta($term_id, 'credit_hours', 3);
        add_term_meta($term_id, 'prerequisites', ['basic_math', 'algebra']);
        
        // Retrieve and verify meta
        $subject_code = get_term_meta($term_id, 'subject_code', true);
        $this->assertEquals('MATH101', $subject_code);
        
        $credit_hours = get_term_meta($term_id, 'credit_hours', true);
        $this->assertEquals(3, $credit_hours);
        
        $prerequisites = get_term_meta($term_id, 'prerequisites', true);
        $this->assertIsArray($prerequisites);
        $this->assertContains('basic_math', $prerequisites);
    }

    /**
     * Test taxonomy term deletion and cleanup.
     */
    public function test_taxonomy_term_deletion() {
        // Create term and assign to post
        $grade_term = wp_insert_term('Grade 5', 'sms_grades');
        $student_id = $this->factory->create_student();
        
        wp_set_post_terms($student_id, [$grade_term['term_id']], 'sms_grades');
        
        // Verify assignment
        $assigned_terms = wp_get_post_terms($student_id, 'sms_grades');
        $this->assertCount(1, $assigned_terms);
        
        // Delete term
        $result = wp_delete_term($grade_term['term_id'], 'sms_grades');
        $this->assertNotWPError($result);
        
        // Verify term is deleted and assignment is removed
        $term = get_term($grade_term['term_id'], 'sms_grades');
        $this->assertNull($term);
        
        $assigned_terms = wp_get_post_terms($student_id, 'sms_grades');
        $this->assertCount(0, $assigned_terms);
    }

    /**
     * Test hierarchical taxonomy operations.
     */
    public function test_hierarchical_taxonomy_operations() {
        // Create parent term
        $primary_term = wp_insert_term('Primary', 'sms_grades');
        
        // Create child terms
        $grade_1_term = wp_insert_term('Grade 1', 'sms_grades', [
            'parent' => $primary_term['term_id']
        ]);
        
        $grade_2_term = wp_insert_term('Grade 2', 'sms_grades', [
            'parent' => $primary_term['term_id']
        ]);
        
        // Test getting children
        $children = get_term_children($primary_term['term_id'], 'sms_grades');
        $this->assertCount(2, $children);
        $this->assertContains($grade_1_term['term_id'], $children);
        $this->assertContains($grade_2_term['term_id'], $children);
        
        // Test getting ancestors
        $ancestors = get_ancestors($grade_1_term['term_id'], 'sms_grades');
        $this->assertCount(1, $ancestors);
        $this->assertEquals($primary_term['term_id'], $ancestors[0]);
    }

    /**
     * Test taxonomy term counting.
     */
    public function test_taxonomy_term_counting() {
        // Create terms
        $grade_5_term = wp_insert_term('Grade 5', 'sms_grades');
        $grade_6_term = wp_insert_term('Grade 6', 'sms_grades');
        
        // Create students and assign grades
        $students = $this->factory->create_students(5);
        
        // Assign 3 students to Grade 5, 2 to Grade 6
        for ($i = 0; $i < 3; $i++) {
            wp_set_post_terms($students[$i], [$grade_5_term['term_id']], 'sms_grades');
        }
        
        for ($i = 3; $i < 5; $i++) {
            wp_set_post_terms($students[$i], [$grade_6_term['term_id']], 'sms_grades');
        }
        
        // Update term counts
        wp_update_term_count_now([$grade_5_term['term_id'], $grade_6_term['term_id']], 'sms_grades');
        
        // Verify counts
        $grade_5 = get_term($grade_5_term['term_id'], 'sms_grades');
        $grade_6 = get_term($grade_6_term['term_id'], 'sms_grades');
        
        $this->assertEquals(3, $grade_5->count);
        $this->assertEquals(2, $grade_6->count);
    }
}