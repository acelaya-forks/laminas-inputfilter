<?php

declare(strict_types=1);

namespace LaminasTest\InputFilter\FileInput;

use Laminas\InputFilter\FileInput;
use Laminas\InputFilter\FileInput\HttpServerFileInputDecorator;
use Laminas\Validator;
use LaminasTest\InputFilter\InputTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Webmozart\Assert\Assert;

use function json_encode;

use const JSON_THROW_ON_ERROR;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

#[CoversClass(HttpServerFileInputDecorator::class)]
#[CoversClass(FileInput::class)]
class HttpServerFileInputDecoratorTest extends InputTest
{
    /** @var HttpServerFileInputDecorator */
    protected $input;

    protected function setUp(): void
    {
        $this->input = new FileInput('foo');
        // Upload validator does not work in CLI test environment, disable
        $this->input->setAutoPrependUploadValidator(false);
    }

    public function testRetrievingValueFiltersTheValue(): void
    {
        self::markTestSkipped('Test are not enabled in FileInputTest');
    }

    public function testRetrievingValueFiltersTheValueOnlyAfterValidating(): void
    {
        $value = ['tmp_name' => 'bar'];
        $this->input->setValue($value);

        $newValue = ['tmp_name' => 'foo'];
        $this->input->setFilterChain($this->createFilterChainMock([[$value, $newValue]]));

        self::assertEquals($value, $this->input->getValue());
        self::assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertEquals($newValue, $this->input->getValue());
    }

    public function testCanFilterArrayOfMultiFileData(): void
    {
        $values = [
            ['tmp_name' => 'foo'],
            ['tmp_name' => 'bar'],
            ['tmp_name' => 'baz'],
        ];
        $this->input->setValue($values);

        $newValue      = ['tmp_name' => 'new'];
        $filteredValue = [$newValue, $newValue, $newValue];
        $this->input->setFilterChain($this->createFilterChainMock([
            [$values[0], $newValue],
            [$values[1], $newValue],
            [$values[2], $newValue],
        ]));

        self::assertEquals($values, $this->input->getValue());
        self::assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertEquals(
            $filteredValue,
            $this->input->getValue()
        );
    }

    public function testCanRetrieveRawValue(): void
    {
        $value = ['tmp_name' => 'bar'];
        $this->input->setValue($value);

        $newValue = ['tmp_name' => 'new'];
        $this->input->setFilterChain($this->createFilterChainMock([[$value, $newValue]]));

        self::assertEquals($value, $this->input->getRawValue());
    }

    public function testValidationOperatesOnFilteredValue(): void
    {
        self::markTestSkipped('Test is not enabled in FileInputTest');
    }

    public function testValidationOperatesBeforeFiltering(): void
    {
        $badValue = [
            'tmp_name' => ' ' . __FILE__ . ' ',
            'name'     => 'foo',
            'size'     => 1,
            'error'    => 0,
        ];
        $this->input->setValue($badValue);

        $filteredValue = ['tmp_name' => 'new'];
        $this->input->setFilterChain($this->createFilterChainMock([[$badValue, $filteredValue]]));
        $this->input->setValidatorChain($this->createValidatorChainMock([[$badValue, null, false]]));

        self::assertFalse($this->input->isValid());
        self::assertEquals($badValue, $this->input->getValue());
    }

    public function testAutoPrependUploadValidatorIsOnByDefault(): void
    {
        $input = new FileInput('foo');
        self::assertTrue($input->getAutoPrependUploadValidator());
    }

    public function testUploadValidatorIsAddedWhenIsValidIsCalled(): void
    {
        $this->input->setAutoPrependUploadValidator(true);
        self::assertTrue($this->input->getAutoPrependUploadValidator());
        self::assertTrue($this->input->isRequired());
        $this->input->setValue([
            'tmp_name' => __FILE__,
            'name'     => 'foo',
            'size'     => 1,
            'error'    => 0,
        ]);
        $validatorChain = $this->input->getValidatorChain();
        self::assertCount(0, $validatorChain->getValidators());

        self::assertFalse($this->input->isValid());
        $validators = $validatorChain->getValidators();
        self::assertCount(1, $validators);
        self::assertInstanceOf(Validator\File\UploadFile::class, $validators[0]['instance']);
    }

    public function testUploadValidatorIsNotAddedWhenIsValidIsCalled(): void
    {
        self::assertFalse($this->input->getAutoPrependUploadValidator());
        self::assertTrue($this->input->isRequired());
        $this->input->setValue(['tmp_name' => 'bar']);
        $validatorChain = $this->input->getValidatorChain();
        self::assertCount(0, $validatorChain->getValidators());

        self::assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages(), JSON_THROW_ON_ERROR)
        );
        self::assertCount(0, $validatorChain->getValidators());
    }

    public function testRequiredUploadValidatorValidatorNotAddedWhenOneExists(): void
    {
        $this->input->setAutoPrependUploadValidator(true);
        self::assertTrue($this->input->getAutoPrependUploadValidator());
        self::assertTrue($this->input->isRequired());
        $this->input->setValue(['tmp_name' => 'bar']);

        $uploadMock = $this->createMock(Validator\File\UploadFile::class);
        $uploadMock->expects(self::exactly(1))
                     ->method('isValid')
                     ->willReturn(true);

        $validatorChain = $this->input->getValidatorChain();
        $validatorChain->prependValidator($uploadMock);
        self::assertTrue(
            $this->input->isValid(),
            'isValid() value not match. Detail . ' . json_encode($this->input->getMessages(), JSON_THROW_ON_ERROR)
        );

        $validators = $validatorChain->getValidators();
        self::assertCount(1, $validators);
        self::assertEquals($uploadMock, $validators[0]['instance']);
    }

    public function testValidationsRunWithoutFileArrayDueToAjaxPost(): void
    {
        $this->input->setAutoPrependUploadValidator(true);
        self::assertTrue($this->input->getAutoPrependUploadValidator());
        self::assertTrue($this->input->isRequired());
        $this->input->setValue('');

        $expectedNormalizedValue = [
            'tmp_name' => '',
            'name'     => '',
            'size'     => 0,
            'type'     => '',
            'error'    => UPLOAD_ERR_NO_FILE,
        ];
        $this->input->setValidatorChain($this->createValidatorChainMock([[$expectedNormalizedValue, null, false]]));
        self::assertFalse($this->input->isValid());
    }

    public function testValidationsRunWithoutFileArrayIsSend(): void
    {
        $this->input->setAutoPrependUploadValidator(true);
        self::assertTrue($this->input->getAutoPrependUploadValidator());
        self::assertTrue($this->input->isRequired());
        $this->input->setValue([]);
        $expectedNormalizedValue = [
            'tmp_name' => '',
            'name'     => '',
            'size'     => 0,
            'type'     => '',
            'error'    => UPLOAD_ERR_NO_FILE,
        ];
        $this->input->setValidatorChain($this->createValidatorChainMock([[$expectedNormalizedValue, null, false]]));
        self::assertFalse($this->input->isValid());
    }

    #[DataProvider('emptyValueProvider')]
    public function testNotEmptyValidatorAddedWhenIsValidIsCalled(mixed $raw, mixed $filtered): void
    {
        self::markTestSkipped('Test is not enabled in FileInputTest');
    }

    #[DataProvider('emptyValueProvider')]
    public function testRequiredNotEmptyValidatorNotAddedWhenOneExists(mixed $raw, mixed $filtered): void
    {
        self::markTestSkipped('Test is not enabled in FileInputTest');
    }

    /**
     * @param null|string|string[] $fallbackValue
     * @param null|string|string[] $originalValue
     * @param null|string|string[] $expectedValue
     */
    public function testFallbackValueVsIsValidRules(
        ?bool $required = null,
        $fallbackValue = null,
        $originalValue = null,
        ?bool $isValid = null,
        $expectedValue = null
    ): void {
        self::markTestSkipped('Input::setFallbackValue is not implemented on FileInput');
    }

    /** @param null|string|string[] $fallbackValue */
    public function testFallbackValueVsIsValidRulesWhenValueNotSet(
        ?bool $required = null,
        $fallbackValue = null
    ): void {
        self::markTestSkipped('Input::setFallbackValue is not implemented on FileInput');
    }

    public function testIsEmptyFileNotArray(): void
    {
        $rawValue = 'file';
        self::assertTrue($this->input->isEmptyFile($rawValue));
    }

    public function testIsEmptyFileUploadNoFile(): void
    {
        $rawValue = [
            'tmp_name' => '',
            'error'    => UPLOAD_ERR_NO_FILE,
        ];
        self::assertTrue($this->input->isEmptyFile($rawValue));
    }

    public function testIsEmptyFileOk(): void
    {
        $rawValue = [
            'tmp_name' => 'name',
            'error'    => UPLOAD_ERR_OK,
        ];
        self::assertFalse($this->input->isEmptyFile($rawValue));
    }

    public function testIsEmptyMultiFileUploadNoFile(): void
    {
        $rawValue = [
            [
                'tmp_name' => 'foo',
                'error'    => UPLOAD_ERR_NO_FILE,
            ],
        ];
        self::assertTrue($this->input->isEmptyFile($rawValue));
    }

    public function testIsEmptyFileMultiFileOk(): void
    {
        $rawValue = [
            [
                'tmp_name' => 'foo',
                'error'    => UPLOAD_ERR_OK,
            ],
            [
                'tmp_name' => 'bar',
                'error'    => UPLOAD_ERR_OK,
            ],
        ];
        self::assertFalse($this->input->isEmptyFile($rawValue));
    }

    public function testDefaultInjectedUploadValidatorRespectsRelease2Convention(): void
    {
        $input          = new FileInput('foo');
        $validatorChain = $input->getValidatorChain();
        $pluginManager  = $validatorChain->getPluginManager();
        $pluginManager->setInvokableClass('fileuploadfile', TestAsset\FileUploadMock::class);
        $input->setValue('');

        self::assertTrue($input->isValid());
    }

    /**
     * Specific FileInput::merge extras
     */
    public function testFileInputMerge(): void
    {
        $source = new FileInput();
        $source->setAutoPrependUploadValidator(true);

        $target = $this->input;
        $target->setAutoPrependUploadValidator(false);

        $return = $target->merge($source);
        self::assertSame($target, $return, 'merge() must return it self');

        self::assertEquals(
            true,
            $target->getAutoPrependUploadValidator(),
            'getAutoPrependUploadValidator() value not match'
        );
    }

    public static function isRequiredVsAllowEmptyVsContinueIfEmptyVsIsValidProvider(): iterable
    {
        $dataSets = parent::isRequiredVsAllowEmptyVsContinueIfEmptyVsIsValidProvider();
        Assert::isArrayAccessible($dataSets);

        // FileInput do not use NotEmpty validator so the only validator present in the chain is the custom one.
        unset($dataSets['Required: T; AEmpty: F; CIEmpty: F; Validator: X, Value: Empty / tmp_name']);
        unset($dataSets['Required: T; AEmpty: F; CIEmpty: F; Validator: X, Value: Empty / single']);
        unset($dataSets['Required: T; AEmpty: F; CIEmpty: F; Validator: X, Value: Empty / multi']);

        return $dataSets;
    }

    public static function emptyValueProvider(): iterable
    {
        return [
            'tmp_name' => [
                'raw'      => 'file',
                'filtered' => [
                    'tmp_name' => 'file',
                    'name'     => 'file',
                    'size'     => 0,
                    'type'     => '',
                    'error'    => UPLOAD_ERR_NO_FILE,
                ],
            ],
            'single'   => [
                'raw'      => [
                    'tmp_name' => '',
                    'error'    => UPLOAD_ERR_NO_FILE,
                ],
                'filtered' => [
                    'tmp_name' => '',
                    'error'    => UPLOAD_ERR_NO_FILE,
                ],
            ],
            'multi'    => [
                'raw'      => [
                    [
                        'tmp_name' => 'foo',
                        'error'    => UPLOAD_ERR_NO_FILE,
                    ],
                ],
                'filtered' => [
                    'tmp_name' => 'foo',
                    'error'    => UPLOAD_ERR_NO_FILE,
                ],
            ],
        ];
    }

    public static function mixedValueProvider(): array
    {
        $fooUploadErrOk = [
            'tmp_name' => 'foo',
            'error'    => UPLOAD_ERR_OK,
        ];

        return [
            'single' => [
                'raw'      => $fooUploadErrOk,
                'filtered' => $fooUploadErrOk,
            ],
            'multi'  => [
                'raw'      => [
                    $fooUploadErrOk,
                ],
                'filtered' => $fooUploadErrOk,
            ],
        ];
    }

    /** @return array<string, string> */
    protected function getDummyValue(bool $raw = true)
    {
        return ['tmp_name' => 'bar'];
    }
}
