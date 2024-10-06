const Save = ({ attributes }) => {
    const { dropdownValue } = attributes;

    return (
        <p>Selected option: {dropdownValue}</p>
    );
};

export default Save;
